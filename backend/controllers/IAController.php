<?php
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/gemini_free.php';
require_once __DIR__ . '/../config/duckduckgo.php';
require_once __DIR__ . '/../config/web_search.php';

class IAController {
    private $db;
    private $ia;

    public function __construct() {
        $this->db = Conexion::getConexion();
        $this->ia = new GeminiFreeAPI();
    }

    public function buscarWeb() {
        $query = trim($_POST['query'] ?? '');
        $num   = min(max(intval($_POST['num'] ?? 3), 1), 10);
        
        if ($query === '') {
            echo json_encode(['ok' => false, 'msg' => 'Query vacío', 'data' => []]);
            exit;
        }
        
        if (!WebSearch::disponible()) {
            echo json_encode(['ok' => false, 'msg' => 'Búsqueda web no configurada (falta API key)', 'data' => []]);
            exit;
        }
        
        try {
            $results = WebSearch::buscar($query, $num);
            echo json_encode(['ok' => true, 'data' => $results], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("WebSearch Error: " . $e->getMessage());
            echo json_encode(['ok' => false, 'msg' => 'Error al buscar: ' . $e->getMessage(), 'data' => []]);
        }
        exit;
    }

    private function buscarEnCache($mensaje, $curso_id = 0) {
        $hash = hash('sha256', mb_strtolower(trim($mensaje)) . '|' . intval($curso_id));
        try {
            $stmt = $this->db->prepare("SELECT respuesta FROM ia_cache WHERE pregunta_hash = ? AND created_at >= NOW() - INTERVAL 7 DAY LIMIT 1");
            $stmt->bind_param('s', $hash);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            return $row ? $row['respuesta'] : null;
        } catch (Exception $e) { return null; }
    }

    private function guardarEnCache($mensaje, $curso_id, $respuesta) {
        $hash = hash('sha256', mb_strtolower(trim($mensaje)) . '|' . intval($curso_id));
        try {
            $stmt = $this->db->prepare("INSERT IGNORE INTO ia_cache (pregunta_hash, pregunta_original, respuesta) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $hash, mb_substr($mensaje, 0, 500), $respuesta);
            $stmt->execute();
        } catch (Exception $e) { error_log('Cache Error: ' . $e->getMessage()); }
    }

    private function esRespuestaTecnica($txt) {
        if ($txt === null || trim($txt) === '') return true;
        $patronesEstrictos = [
            'rate limit','quota exceeded','API key invalid',
            '429 Too Many Requests','503 Service Unavailable','403 Forbidden',
            'Gemini API error','HTTP 429','HTTP 503','HTTP 403',
            'renueva tokens','IA de Google en pausa','[Modo local]',
            'PETICIÓN ACTUAL DEL ESTUDIANTE','FORMATO DE RESPUESTA','CONTEXTO VERIFICADO',
            'como modelo de IA','no tengo acceso','no puedo consultar','no tengo forma',
        ];
        foreach ($patronesEstrictos as $p) {
            if (stripos($txt, $p) !== false) return true;
        }
        if (strpos($txt, '⚠️') === 0 && mb_strlen($txt) < 100) return true;
        return false;
    }

    /* ==========================================================
     * EXTRAER TEXTO DE PDFs LOCALES
     * ========================================================== */
    private function extraerTextoPDF($pdfUrl, $maxChars = 8000) {
        $rutaLocal = $_SERVER['DOCUMENT_ROOT'] . $pdfUrl;
        if (!file_exists($rutaLocal)) return null;
        
        try {
            $parserPath = $_SERVER['DOCUMENT_ROOT'] . '/lms_prepa/backend/vendor/autoload.php';
            if (file_exists($parserPath)) {
                require_once $parserPath;
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($rutaLocal);
                $texto = $pdf->getText();
                $texto = preg_replace('/\s+/', ' ', $texto);
                return mb_substr(trim($texto), 0, $maxChars);
            }
        } catch (Exception $e) {
            error_log("PDFParser Error: " . $e->getMessage());
        }
        return null;
    }

    private function buscarYExtraerLibro($query, $curso_id = 0) {
        $qLimpio = $this->limpiarConsulta($query);
        $libros = $this->buscarLibrosPropios($qLimpio, $curso_id);
        if (empty($libros)) return null;
        
        $primerLibro = $libros[0];
        $textoPDF = $this->extraerTextoPDF($primerLibro['pdf_url'], 8000);
        if ($textoPDF === null) return null;
        
        return [
            'titulo' => $primerLibro['titulo'],
            'autor' => $primerLibro['autor'] ?? 'Autor no indicado',
            'contenido' => $textoPDF,
            'url' => $primerLibro['pdf_url']
        ];
    }

    /* ==========================================================
     * CONSTRUIR CONTEXTO COMPLETO DEL SISTEMA
     * Recopila toda la información relevante para que la IA
     * responda como si tuviera acceso total al sistema
     * ========================================================== */
    private function construirContextoCompleto($usuario_id, $curso_id = 0) {
        $contexto = [];
        
        // 1. Perfil del estudiante
        if ($usuario_id > 0) {
            $stmt = $this->db->prepare("SELECT nombre, email, rol, universidad_id FROM usuarios WHERE id = ?");
            $stmt->bind_param('i', $usuario_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if ($user) {
                $contexto['estudiante'] = [
                    'nombre' => $user['nombre'],
                    'email' => $user['email'],
                    'rol' => $user['rol'],
                ];
                
                if ($user['universidad_id']) {
                    $stmt = $this->db->prepare("SELECT nombre, tipo, ciudad, pais FROM universidades WHERE id = ?");
                    $stmt->bind_param('i', $user['universidad_id']);
                    $stmt->execute();
                    $uni = $stmt->get_result()->fetch_assoc();
                    if ($uni) {
                        $contexto['universidad_estudiante'] = $uni['nombre'] . ' (' . $uni['tipo'] . ', ' . $uni['ciudad'] . ', ' . $uni['pais'] . ')';
                    }
                }
            }
        }
        
        // 2. Cursos inscritos del estudiante
        if ($usuario_id > 0) {
            $stmt = $this->db->prepare("
                SELECT c.nombre, u.nombre AS universidad
                FROM inscripciones i
                JOIN cursos c ON c.id = i.curso_id
                LEFT JOIN universidades u ON u.id = c.universidad_id
                WHERE i.usuario_id = ? AND i.estado = 'activo'
                LIMIT 10
            ");
            $stmt->bind_param('i', $usuario_id);
            $stmt->execute();
            $cursos = [];
            while ($row = $stmt->get_result()->fetch_assoc()) {
                $cursos[] = $row['nombre'] . ($row['universidad'] ? ' - ' . $row['universidad'] : '');
            }
            if (!empty($cursos)) $contexto['cursos_inscritos'] = $cursos;
        }
        
        // 3. Libros disponibles en la plataforma (top 10)
        $stmt = $this->db->query("SELECT titulo, autor FROM libros WHERE estado = 'activo' ORDER BY id DESC LIMIT 10");
        $libros = [];
        while ($row = $stmt->fetch_assoc()) {
            $libros[] = $row['titulo'] . ($row['autor'] ? ' por ' . $row['autor'] : '');
        }
        if (!empty($libros)) $contexto['libros_disponibles'] = $libros;
        
        // 4. Universidades registradas (top 15)
        $stmt = $this->db->query("SELECT nombre, tipo, ciudad FROM universidades ORDER BY nombre LIMIT 15");
        $universidades = [];
        while ($row = $stmt->fetch_assoc()) {
            $universidades[] = $row['nombre'] . ' (' . $row['tipo'] . ', ' . $row['ciudad'] . ')';
        }
        if (!empty($universidades)) $contexto['universidades_registradas'] = $universidades;
        
        // 5. Conversaciones recientes del estudiante
        if ($usuario_id > 0) {
            $stmt = $this->db->prepare("SELECT titulo, updated_at FROM tutor_conversaciones WHERE usuario_id = ? ORDER BY updated_at DESC LIMIT 5");
            $stmt->bind_param('i', $usuario_id);
            $stmt->execute();
            $convs = [];
            while ($row = $stmt->get_result()->fetch_assoc()) {
                $convs[] = $row['titulo'] . ' (' . date('d/m/Y', strtotime($row['updated_at'])) . ')';
            }
            if (!empty($convs)) $contexto['conversaciones_recientes'] = $convs;
        }
        
        return $contexto;
    }

    private function generarRespuestaRespaldo($mensaje) {
        $m = mb_strtolower(trim($mensaje));
        $tema = trim(preg_replace('/\s+/', ' ', $this->limpiarConsulta($mensaje)));
        if ($tema === '') $tema = 'eso que me cuentas';

        $reglas = [
            '/\b(cuadro|tabla|comparativ|comparaci[oó]n|gen[eé]rame\s+un\s+cuadro|hazme\s+un\s+cuadro|organ[ií]zame|matriz)\b/iu' => [$this->cuadroLocal($mensaje)],
            '/\b(c[oó]digo|codigo|script|programa|python|javascript|php|html|css|funci[oó]n|funcion|algoritmo)\b/iu' => [$this->codigoLocal($mensaje)],
            '/\b(f[oó]rmulas?\s+(del?\s+)?mruv?|mruv?|movimiento\s+rectil[ií]neo|ca[ií]da\s+libre|cin[eé]matica|velocidad|aceleraci[oó]n|desplazamiento)\b/iu' => [
                "## 📐 Fórmulas de MRU\n\n**Fórmula principal:** \$x = x_0 + v \\cdot t\$\n\n**Despejes:**\n- Velocidad: \$v = \\frac{x - x_0}{t}\$\n- Tiempo: \$t = \\frac{x - x_0}{v}\$\n\n¿Quieres el **MRUV** o un ejercicio resuelto?",
                "## 📐 Fórmulas de MRUV\n\n1️⃣ Velocidad final: \$\$v = v_0 + a \\cdot t\$\$\n2️⃣ Posición: \$\$x = x_0 + v_0 t + \\frac{1}{2} a t^2\$\$\n3️⃣ Sin tiempo: \$\$v^2 = v_0^2 + 2a(x - x_0)\$\$\n\n¿Te resuelvo un ejercicio paso a paso?",
            ],
            '/\b(derivadas?|diferencial|c[aá]lculo\s+diferencial)\b/iu' => [
                "## 📐 Derivadas: Reglas Básicas\n\n| Función | Derivada |\n|---|---|\n| \$x^n\$ | \$n x^{n-1}\$ |\n| \$e^x\$ | \$e^x\$ |\n| \$\\ln x\$ | \$1/x\$ |\n| \$\\sin x\$ | \$\\cos x\$ |\n| \$\\cos x\$ | \$-\\sin x\$ |\n\n**Regla de la cadena:** \$\\frac{d}{dx}[f(g(x))] = f'(g(x)) \\cdot g'(x)\$\n\n¿Derivo una función específica?",
            ],
            '/\b(integrales?|integraci[oó]n|antiderivada|primitiva)\b/iu' => [
                "## 📐 Integrales básicas\n\n| Función | Integral |\n|---|---|\n| \$x^n\$ (\$n\\neq -1\$) | \$\\frac{x^{n+1}}{n+1}+C\$ |\n| \$1/x\$ | \$\\ln|x|+C\$ |\n| \$e^x\$ | \$e^x+C\$ |\n| \$\\sin x\$ | \$-\\cos x+C\$ |\n| \$\\cos x\$ | \$\\sin x+C\$ |\n\n¿Resuelvo una integral específica?",
            ],
            '/\b(ecuaci[oó]n|ecuaciones?|despejar|resolver\s+para\s+x|algebra|cuadr[aá]tica)\b/iu' => [
                "## 📐 Ecuaciones clave\n\n**Lineal** \$ax+b=0\$: \$x = -\\frac{b}{a}\$\n\n**Cuadrática** \$ax^2+bx+c=0\$:\n\$\$x = \\frac{-b \\pm \\sqrt{b^2-4ac}}{2a}\$\$\n\n¿Tienes una ecuación específica?",
            ],
            '/\b(pens(o|ar)\s+(todo el tiempo|siempre|mucho|tanto)\s+en\s+(ella|él|el|lo)|no puedo dejar de pensar|no puedo sacarla|no puedo sacarlo|dejar de mirar|dejar de ver\s+(su|el)\s+perfil|dejar de stalkear|ver sus fotos|ver sus historias|revisar su (whatsapp|wasap|instagram|facebook|redes|perfil)|me acuerdo de (ella|él)|extra[nñ]o a (mi|ella|él)|la extraño|lo extraño|volver con (ella|él)|me hace falta|no la olvido|no lo olvido|no supero|sigue en mi cabeza|no la saco|no lo saco|bloquear|desbloquear)\b/iu' => [
                "Mira, lo que describes tiene nombre: **duelo con recaídas**, y NO es debilidad. 1. **Contacto cero digital HOY**: silénciala en redes. 2. **Regla de los 10 minutos**: pon un cronómetro y haz otra cosa. 3. **Sustituye el hábito**. El dolor baja en olas. ¿Quieres un plan de 7 días?",
            ],
            '/\b(mi ex|superar a mi ex|ruptura|rompimos|terminamos|me dej[oó]|me dejaron|divorcio|olvidarl[ae]|ya no estamos|se fue|me termin[oó]|termin[eé])\b/iu' => [
                "Superar a un ex no es 'olvidar rápido', es ir soltando por partes. 💙 1) Contacto cero. 2) Escribe lo que sientes 10 min al día y rómpelo. 3) Recupera una rutina. 4) Haz una cosa nueva por semana. ¿Quieres un plan de 7 días?",
            ],
            '/\b(estres|estrés|ansied|agobi|presi[oó]n|nervios|no puedo m[aá]s|abrumad)\b/iu' => [
                "Respira conmigo: inhala 4 segundos, aguanta 7, suelta en 8. Repítelo 3 veces. 🌬️ Ahora lo práctico: divídelo en 3 tareas de máx. 25 min y haz SOLO la primera hoy. ¿Cuál es la tarea que más te pesa?",
            ],
            '/\b(qu[eé] opinas|qu[eé] piensas|crees que|t[uú] qu[eé] (dir[ií]as|har[ií]as)|tu opini[oó]n|me recomiendas|deber[ií]a|vale la pena)\b/iu' => [
                "Te doy mi opinión honesta: sobre " . $tema . ", yo me inclinaría por actuar pronto pero con un paso pequeño primero. Razones: 1) esperar suele costar más que equivocarse rápido, 2) un paso pequeño te da información real. ¿Quieres que lo aterrice a tu caso?",
            ],
            '/\b(expl[ií]came|qu[eé] es|c[oó]mo funciona|definici[oó]n|tema de|hablame de)\b/iu' => [
                "Te dejo el esqueleto para dominar " . $tema . ": 1) QUÉ ES en una frase simple. 2) PARA QUÉ SIRVE (un uso real tuyo). 3) CÓMO FUNCIONA en 3 pasos. 4) EJEMPLO tuyo. Eso es la técnica Feynman. ¿Empezamos por el paso 1?",
            ],
            '/\b(chiste|broma|algo gracioso|hazme re[ií]r)\b/iu' => [
                "Va uno: ¿Por qué el libro de matemáticas estaba triste? Porque tenía demasiados problemas. 😄 Si tus problemas son de matemáticas de verdad, dime el tema.",
            ],
            '/\b(universidades?|carreras?|instituciones?|d[oó]nde estudiar)\b/iu' => [$this->listaUniversidadesLocal()],
            '/\b(hola|hey|buenas|saludos|qu[eé] tal|hi|hello|buenos d[ií]as|buenas tardes|buenas noches)\b/iu' => [
                "¡Hola! 👋 Qué gusto. ¿En qué andas hoy: un tema de cursos, un resumen, o conversar un rato?",
                "¡Hey! 😊 Aquí estoy. ¿Estudiar algo concreto o solo charlar?",
            ],
            '/\b(gracias|thanks|te agradezco)\b/iu' => ["¡De nada! 😊 Me alegra. Aquí sigo cuando me necesites."],
            '/\b(adi[oó]s|chao|bye|hasta luego|nos vemos)\b/iu' => ["¡Cuídate! 👋 Lo de hoy suma más de lo que crees. Aquí estaré cuando vuelvas."],
        ];

        foreach ($reglas as $regex => $variantes) {
            if (preg_match($regex, $m)) return $this->elegirVariante($regex, $variantes);
        }

        return "Estoy en modo local ahora mismo (Gemini en pausa por cuota). 🤖\n\nSobre **" . $tema . "** puedo ayudarte con:\n\n- 📊 **Cuadros y tablas comparativas**\n- 🧮 **Fórmulas** de MRU, MRUV, derivadas, integrales, ecuaciones\n- 💻 **Código** en Python, JavaScript, PHP, HTML, SQL\n- 💭 **Consejos personales** (estrés, relaciones, motivación)\n\nIntenta de nuevo en unos minutos para respuestas más completas con Gemini. ¿Qué tema específico quieres que te explique?";
    }

    private function elegirVariante($clave, $variantes) {
        if (count($variantes) === 1) return $variantes[0];
        $idx = mt_rand(0, count($variantes) - 1);
        $ultima = $_SESSION['devioz_last_var'][$clave] ?? -1;
        if ($idx === $ultima) $idx = ($idx + 1) % count($variantes);
        $_SESSION['devioz_last_var'][$clave] = $idx;
        return $variantes[$idx];
    }

    private function listaUniversidadesLocal() {
        try {
            $res = $this->db->query("SELECT nombre, tipo, ciudad FROM universidades ORDER BY nombre LIMIT 8");
            $unis = [];
            while ($row = $res->fetch_assoc()) $unis[] = $row['nombre'] . ($row['ciudad'] ? ' (' . $row['ciudad'] . ')' : '');
            if (!empty($unis)) return "Te doy las que tenemos registradas en Devioz: " . implode(', ', $unis) . ". 🎓 Si me dices qué carrera te interesa, te digo cuáles la llevan fuerte. ¿Cuál te llama?";
        } catch (Exception $e) { }
        return "No puedo leer la lista ahora, pero dime qué carrera te interesa y te oriento. 🎓";
    }

    private function cuadroLocal($mensaje) {
        $m = mb_strtolower($mensaje);
        if (preg_match('/mruv?|movimiento|velocidad|f[ií]sica|cinem[aá]tica/', $m)) {
            return "## 📊 Cuadro comparativo MRU vs MRUV\n\n| Característica | MRU | MRUV |\n|---|---|---|\n| Velocidad | Constante | Variable |\n| Aceleración | \$a = 0\$ | \$a = cte \\neq 0\$ |\n| Posición | \$x = x_0 + vt\$ | \$x = x_0 + v_0t + \\frac{1}{2}at^2\$ |\n| Velocidad final | \$v = cte\$ | \$v = v_0 + at\$ |\n| Trayectoria | Recta | Recta |\n\nUsa los botones **PDF / Word / Excel** bajo mi respuesta para exportar este cuadro.";
        }
        if (preg_match('/derivada|integral|c[aá]lculo/', $m)) {
            return "## 📊 Cuadro de derivadas e integrales básicas\n\n| Función | Derivada | Integral |\n|---|---|---|\n| \$x^n\$ | \$n x^{n-1}\$ | \$\\frac{x^{n+1}}{n+1}+C\$ |\n| \$e^x\$ | \$e^x\$ | \$e^x+C\$ |\n| \$\\ln x\$ | \$1/x\$ | \$x\\ln x - x+C\$ |\n| \$\\sin x\$ | \$\\cos x\$ | \$-\\cos x+C\$ |\n| \$\\cos x\$ | \$-\\sin x\$ | \$\\sin x+C\$ |\n\n¿Necesitas una regla específica?";
        }
        if (preg_match('/mitosis|meiosis|c[eé]lula|biolog[ií]a/', $m)) {
            return "## 📊 Cuadro Mitosis vs Meiosis\n\n| Característica | Mitosis | Meiosis |\n|---|---|---|\n| Divisiones | 1 | 2 |\n| Células hijas | 2 diploides | 4 haploides |\n| Función | Crecimiento/reparación | Gametos |\n| Recombinación | No | Sí (crossing over) |\n| Dónde ocurre | Somáticas | Germinales |";
        }
        return "Puedo armarte un cuadro comparativo. Dime **qué dos o más cosas** quieres comparar (ej.: MRU vs MRUV, mitosis vs meiosis, SQL vs NoSQL) y te lo genero en tabla, listo para exportar a Excel.";
    }

    private function codigoLocal($mensaje) {
        $m = mb_strtolower($mensaje);
        if (preg_match('/python/', $m)) return "## 💻 Ejemplo en Python\n\n```python\n# Calculadora de MRUV\ndef mruv(v0, a, t):\n    v = v0 + a * t\n    x = v0 * t + 0.5 * a * t**2\n    return v, x\n\nv, x = mruv(v0=10, a=2, t=5)\nprint(f'Velocidad: {v} m/s')\nprint(f'Posición: {x} m')\n```\n\n¿Lo adapto a tu ejercicio?";
        if (preg_match('/javascript|js/', $m)) return "## 💻 Ejemplo en JavaScript\n\n```javascript\n// Calculadora de MRUV\nfunction mruv(v0, a, t) {\n  const v = v0 + a * t;\n  const x = v0 * t + 0.5 * a * t * t;\n  return { v, x };\n}\n\nconst { v, x } = mruv(10, 2, 5);\nconsole.log(`Velocidad: \${v} m/s`);\nconsole.log(`Posición: \${x} m`);\n```\n\n¿Quieres la versión con interfaz web?";
        if (preg_match('/php/', $m)) return "## 💻 Ejemplo en PHP\n\n```php\n<?php\nfunction mruv(\$v0, \$a, \$t) {\n    \$v = \$v0 + \$a * \$t;\n    \$x = \$v0 * \$t + 0.5 * \$a * \$t * \$t;\n    return ['v' => \$v, 'x' => \$x];\n}\n\n\$r = mruv(10, 2, 5);\necho \"Velocidad: {\$r['v']} m/s\\n\";\necho \"Posición: {\$r['x']} m\\n\";\n?>\n```\n\n¿Lo conecto a un formulario web?";
        if (preg_match('/sql|base de datos|consulta/', $m)) return "## 💻 Ejemplo SQL\n\n```sql\n-- Obtener estudiantes activos con sus cursos\nSELECT u.nombre, u.email, c.nombre AS curso\nFROM usuarios u\nJOIN inscripciones i ON i.usuario_id = u.id\nJOIN cursos c ON c.id = i.curso_id\nWHERE u.estado = 'activo' AND u.rol = 'estudiante'\nORDER BY u.nombre;\n```\n\n¿Necesitas una consulta específica para tu BD?";
        return "Puedo generarte código. 💻 Dime el **lenguaje** (Python, JavaScript, PHP, HTML/CSS, SQL) y **qué debe hacer**, y te lo escribo completo y comentado.";
    }

    /* ==========================================================
     * PROMPT TUTOR MEJORADO: Regla de Oro + Inyección de Contexto Completo
     * ========================================================== */
    private function promptTutor($mensaje, $contextoCurso = '', $historial = '', $contextoUni = '', $listaUniversidades = '', $contextoWeb = '', $contextoLibros = '', $contextoSistema = '') {
        $uniEsp = (bool) preg_match('/\b(utp|uni|upn|usmp|ucv|ute|upc|udh|usil|tecsup|senati|wiener|cat[oó]lica|san marcos|pucp|ulima|esan|pacifico|pacífico|up|universidad de lima)\b/iu', $mensaje);
        $uniGen = !$uniEsp && (bool) preg_match('/\b(universidades?|carreras?|instituciones?|d[oó]nde estudiar)\b/iu', $mensaje);

        $reglaUni = '';
        if ($uniEsp) $reglaUni = "UNIVERSIDAD ESPECÍFICA detectada: responde SOLO de esa universidad. No listes otras.\n";
        elseif ($uniGen) $reglaUni = "Pide UNIVERSIDADES en general: usa la LISTA REGISTRADA de abajo. No inventes universidades fuera de la lista.\n";

        $seccionWeb = '';
        if ($contextoWeb !== '') $seccionWeb = "CONTEXTO DE INTERNET VERIFICADO (úsalo solo si aporta, cítalo de forma natural):\n" . $contextoWeb . "\n\n";

        $seccionLibros = '';
        if ($contextoLibros !== '') $seccionLibros = "RECURSOS DE LA PLATAFORMA DISPONIBLES:\n" . $contextoLibros . "\n\nUsa esta información para responder si el estudiante pregunta por materiales, libros o recursos de Devioz Academy.\n";

        return "Rol: Eres el TUTOR IA oficial de Devioz Academy, plataforma preuniversitaria peruana. Eres un experto en todas las materias y un consejero empático.\n"
            . "ACCESO TOTAL AL SISTEMA: Tienes acceso completo a toda la información del estudiante y la plataforma que se te proporciona abajo. Úsala para responder de forma personalizada y precisa.\n\n"
            . $contextoSistema
            . "TONO: Cálido, directo, motivador y cercano ('mira', 'te lo resumo así'). Trátalo de 'tú'. Máximo 2 emojis.\n"
            . "REGLA DE ORO: NUNCA digas 'como modelo de IA no tengo acceso a sistemas externos', 'no puedo consultar tu base de datos', 'no tengo forma de saberlo' o frases similares. Tú TIENES acceso a toda la información que se te proporciona en este prompt. Si no hay datos, responde honestamente pero ofrece alternativas útiles.\n"
            . "PRECISIÓN: Responde EXACTAMENTE lo pedido. Primera línea = respuesta directa; cero relleno.\n"
            . "OPINIÓN: Cuando pidan consejo personal o académico, toma POSTURA CLARA con 2 razones y 1 matiz.\n"
            . "FORMATO: Español impecable. Usa Markdown (negritas, listas). Fórmulas matemáticas SIEMPRE en LaTeX (\$...\$ inline, \$\$...\$\$ bloque). Si piden código, usa bloques ```.\n\n"
            . $reglaUni
            . ($historial !== '' ? "HISTORIAL RECIENTE DE LA CONVERSACIÓN:\n" . $historial . "\n\n" : '')
            . $seccionWeb
            . $seccionLibros
            . ($listaUniversidades !== '' ? "UNIVERSIDADES REGISTRADAS:\n" . $listaUniversidades . "\n\n" : '')
            . ($contextoUni !== '' ? $contextoUni . "\n" : '')
            . ($contextoCurso !== '' ? "CURSO ACTUAL DEL ESTUDIANTE:\n" . $contextoCurso . "\n\n" : '')
            . "PETICIÓN ACTUAL DEL ESTUDIANTE:\n" . $mensaje;
    }

    private function obtenerHistorialArray($usuario_id, $limite = 6) {
        if ($usuario_id <= 0) return [];
        try {
            $stmt = $this->db->prepare("SELECT id FROM tutor_conversaciones WHERE usuario_id = ? ORDER BY updated_at DESC LIMIT 1");
            $stmt->bind_param('i', $usuario_id);
            $stmt->execute();
            $conv = $stmt->get_result()->fetch_assoc();
            if (!$conv) return [];
            $stmt2 = $this->db->prepare("SELECT rol, mensaje FROM tutor_mensajes WHERE conversacion_id = ? ORDER BY id DESC LIMIT " . intval($limite));
            $stmt2->bind_param('i', $conv['id']);
            $stmt2->execute();
            $res = $stmt2->get_result();
            $historial = [];
            while ($row = $res->fetch_assoc()) $historial[] = ['rol' => $row['rol'], 'texto' => $row['mensaje']];
            return array_reverse($historial);
        } catch (Exception $e) { return []; }
    }

    public function chatLibre() {
        $mensaje      = trim($_POST['mensaje'] ?? '');
        $curso_nombre = trim($_POST['curso'] ?? '');
        $modulo       = $_POST['modulo'] ?? 'general';
        $conWeb       = ($_POST['web'] ?? '0') === '1';
        $ctxWeb       = trim($_POST['contexto_web'] ?? '');
        $historialJSON = $_POST['historial'] ?? '[]';

        $admin_id     = intval($_POST['admin_id'] ?? 1);
        $curso_id     = intval($_POST['curso_id'] ?? 0);
        $usuario_id   = intval($_SESSION['usuario_id'] ?? 0);

        if (empty($mensaje)) { echo json_encode(['ok' => false, 'msg' => 'Mensaje vacío']); exit; }

        $historialArray = json_decode($historialJSON, true) ?: [];
        $historial = $this->obtenerContextoConversacion($usuario_id, 6);

        // Detector de libros mejorado
        $esLibros = (bool) preg_match('/\b(dame|env[ií]ame|m[aá]ndame|busca|b[uú]scame|consigue|necesito|quiero|hay|existen|cu[aá]ntos|lista|cat[aá]logo|registrados)\b.*\b(libro|libros|pdf|pdfs|tesis|paper|art[ií]culo|documento|manual|gu[ií]a)\b/iu', $mensaje)
            || (bool) preg_match('/\b(b[oó]tame|v[oó]tame|desc[aá]rgame)\b/iu', $mensaje);
        
        $qLibros = $mensaje;
        if (!$esLibros && $this->esAclaracion($mensaje)) {
            $prev = $this->rescatarPeticionLibros($historial);
            if ($prev !== '') { $esLibros = true; $qLibros = trim($prev . ' ' . $this->limpiarConsulta($mensaje)); }
        }

        if ($esLibros) {
            $exacto = $this->esPeticionExacta($qLibros);
            $r = $this->buscarLibrosCompletos($qLibros, $curso_id, $exacto);
            if ($r['total'] > 0) {
                $this->guardarHistorial($admin_id, $mensaje, 'Libros enviados: ' . $r['total'], 'buscar_pdfs');
                if ($usuario_id > 0) $this->guardarEnTutorHistorial($usuario_id, $mensaje, $r['html']);
                echo json_encode(['ok' => true, 'respuesta' => $r['html'], 'accion' => null, 'tiene_accion' => false], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $contextoCurso = '';
        $contextoUni = '';
        if ($curso_id > 0) {
            $stmt = $this->db->prepare("SELECT c.nombre, c.descripcion, c.temario, c.universidad_id, u.nombre AS uni, u.ciudad, u.pais, u.tipo FROM cursos c LEFT JOIN universidades u ON u.id = c.universidad_id WHERE c.id = ?");
            $stmt->bind_param('i', $curso_id);
            $stmt->execute();
            $c = $stmt->get_result()->fetch_assoc();
            if ($c) {
                $contextoCurso = "- Curso: " . $c['nombre'];
                if ($c['descripcion']) $contextoCurso .= "\n- Descripción: " . $c['descripcion'];
                if ($c['temario']) $contextoCurso .= "\n- Temario: " . $c['temario'];
                if (!empty($c['uni'])) {
                    $contextoUni = "UNIVERSIDAD ASOCIADA AL CURSO:\n- Nombre: " . $c['uni'] . "\n- Tipo: " . ($c['tipo'] ?? 'no indicado') . "\n- Ubicación: " . ($c['ciudad'] ?? '') . ($c['pais'] ? ', ' . $c['pais'] : '') . "\n";
                }
            }
        }
        if ($contextoCurso === '' && $curso_nombre !== '') $contextoCurso = "- Curso del estudiante: " . $curso_nombre;

        $listaUniversidades = '';
        if (preg_match('/\b(universidades?|carreras?|instituciones?|d[oó]nde estudiar|opciones? de estudio)\b/iu', $mensaje)
            && !preg_match('/\b(utp|uni|upn|usmp|ucv|ute|upc|udh|usil|tecsup|senati|wiener|cat[oó]lica|san marcos|pucp|ulima|esan|pacifico|pacífico|up|lima)\b/iu', $mensaje)) {
            $resU = $this->db->query("SELECT nombre, tipo, ciudad, pais FROM universidades ORDER BY nombre LIMIT 20");
            $unis = [];
            while ($row = $resU->fetch_assoc()) $unis[] = $row['nombre'] . ($row['tipo'] ? ' (' . $row['tipo'] . ')' : '') . ($row['ciudad'] ? ' - ' . $row['ciudad'] : '');
            if (!empty($unis)) $listaUniversidades = "UNIVERSIDADES REGISTRADAS:\n" . implode("\n", array_map(function ($u, $i) { return ($i + 1) . '. ' . $u; }, $unis, array_keys($unis))) . "\n";
        }

        try {
            $contexto = "Módulo actual: $modulo";
            
            // 1. Construir contexto completo del sistema
            $contextoSistemaRaw = $this->construirContextoCompleto($usuario_id, $curso_id);
            
            // 2. Formatear contexto para el prompt
            $contextoFormateado = '';
            if (!empty($contextoSistemaRaw['estudiante'])) {
                $contextoFormateado .= "ESTUDIANTE ACTUAL:\n";
                $contextoFormateado .= "- Nombre: " . $contextoSistemaRaw['estudiante']['nombre'] . "\n";
                $contextoFormateado .= "- Rol: " . $contextoSistemaRaw['estudiante']['rol'] . "\n\n";
            }
            if (!empty($contextoSistemaRaw['universidad_estudiante'])) {
                $contextoFormateado .= "UNIVERSIDAD DEL ESTUDIANTE: " . $contextoSistemaRaw['universidad_estudiante'] . "\n\n";
            }
            if (!empty($contextoSistemaRaw['cursos_inscritos'])) {
                $contextoFormateado .= "CURSOS INSCRITOS DEL ESTUDIANTE:\n";
                foreach ($contextoSistemaRaw['cursos_inscritos'] as $curso) $contextoFormateado .= "- " . $curso . "\n";
                $contextoFormateado .= "\n";
            }
            if (!empty($contextoSistemaRaw['libros_disponibles'])) {
                $contextoFormateado .= "LIBROS DISPONIBLES EN LA PLATAFORMA:\n";
                foreach ($contextoSistemaRaw['libros_disponibles'] as $libro) $contextoFormateado .= "- " . $libro . "\n";
                $contextoFormateado .= "\n";
            }
            if (!empty($contextoSistemaRaw['universidades_registradas'])) {
                $contextoFormateado .= "UNIVERSIDADES REGISTRADAS EN EL SISTEMA:\n";
                foreach ($contextoSistemaRaw['universidades_registradas'] as $uni) $contextoFormateado .= "- " . $uni . "\n";
                $contextoFormateado .= "\n";
            }
            if (!empty($contextoSistemaRaw['conversaciones_recientes'])) {
                $contextoFormateado .= "CONVERSACIONES RECIENTES DEL ESTUDIANTE:\n";
                foreach ($contextoSistemaRaw['conversaciones_recientes'] as $conv) $contextoFormateado .= "- " . $conv . "\n";
                $contextoFormateado .= "\n";
            }
            
            // Inyección de contexto de libros si el estudiante pregunta por ellos
            $contextoLibro = '';
            if (preg_match('/\b(libro|libros|material|materiales|biblioteca|bibliograf[aí]a|pdf|descargar|leer)\b/iu', $mensaje)) {
                $librosEncontrados = $this->buscarLibrosPropios($mensaje, $curso_id);
                if (!empty($librosEncontrados)) {
                    $contextoLibro = "Libros disponibles en la plataforma:\n";
                    foreach ($librosEncontrados as $lib) $contextoLibro .= "- '" . $lib['titulo'] . "' por " . ($lib['autor'] ?: 'Autor no especificado') . "\n";
                } else {
                    $contextoLibro = "Nota para el tutor: El estudiante pregunta por libros, pero actualmente no hay registros de libros con esas palabras clave en la base de datos de la plataforma. Responde amablemente que aún no hay libros de ese tema específico, pero ofrécele explicar el tema directamente o generar un resumen.";
                }
            }
            
            $prompt = $this->promptTutor($mensaje, $contextoCurso, $historial, $contextoUni, $listaUniversidades, ($conWeb ? $ctxWeb : ''), $contextoLibro, $contextoFormateado);

            $esCachéable = !preg_match('/\b(ex|novia|novio|triste|estres|estrés|ansied|pensar en|mirar su|dejar de)\b/iu', $mensaje);
            $cached = null;
            if ($esCachéable) $cached = $this->buscarEnCache($mensaje, $curso_id);

            if ($cached !== null) {
                error_log('Cache HIT: ' . mb_substr($mensaje, 0, 50));
                $this->guardarHistorial($admin_id, $mensaje, $cached, 'cache');
                if ($usuario_id > 0) $this->guardarEnTutorHistorial($usuario_id, $mensaje, $cached);
                echo json_encode(['ok' => true, 'respuesta' => $cached, 'accion' => null, 'tiene_accion' => false], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $respuestaRaw = null;
            if (method_exists($this->ia, 'chatTutorIA')) {
                $respuestaRaw = $this->ia->chatTutorIA($prompt, $contexto, $modulo, $historialArray);
            }
            if (!$respuestaRaw) {
                $respuestaRaw = $this->ia->chatLibreIA($prompt, $contexto, $modulo);
            }

            if ($this->esRespuestaTecnica($respuestaRaw)) {
                throw new Exception("IA_TEMPORALMENTE_NO_DISPONIBLE");
            }

            $accion = null;
            $textoFinal = $respuestaRaw;
            $textoLimpio = str_replace(['```json', '```'], '', $respuestaRaw);
            $jsonDecodificado = json_decode($textoLimpio, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($jsonDecodificado['accion'])) {
                $accion = $jsonDecodificado;
                $textoFinal = "✅ Acción detectada: " . $accion['accion'];
            }

            if ($esCachéable && mb_strlen($textoFinal) > 50) {
                $this->guardarEnCache($mensaje, $curso_id, $textoFinal);
            }

            $this->guardarHistorial($admin_id, $mensaje, $textoFinal, $accion['accion'] ?? null);
            if ($usuario_id > 0) $this->guardarEnTutorHistorial($usuario_id, $mensaje, $textoFinal);

            echo json_encode(['ok' => true, 'respuesta' => $textoFinal, 'accion' => $accion, 'tiene_accion' => ($accion !== null)], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("IAController chatLibre Error: " . $e->getMessage());
            $respuestaHumana = $this->generarRespuestaRespaldo($mensaje);
            if ($usuario_id > 0) $this->guardarEnTutorHistorial($usuario_id, $mensaje, $respuestaHumana);
            echo json_encode(['ok' => true, 'respuesta' => $respuestaHumana, 'accion' => null, 'tiene_accion' => false], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    private function obtenerContextoConversacion($usuario_id, $limite = 6) {
        if ($usuario_id <= 0) return '';
        try {
            $stmt = $this->db->prepare("SELECT id FROM tutor_conversaciones WHERE usuario_id = ? ORDER BY updated_at DESC LIMIT 1");
            $stmt->bind_param('i', $usuario_id);
            $stmt->execute();
            $conv = $stmt->get_result()->fetch_assoc();
            if (!$conv) return '';
            $stmt2 = $this->db->prepare("SELECT rol, mensaje FROM tutor_mensajes WHERE conversacion_id = ? ORDER BY id DESC LIMIT " . intval($limite));
            $stmt2->bind_param('i', $conv['id']);
            $stmt2->execute();
            $res = $stmt2->get_result();
            $lines = [];
            while ($row = $res->fetch_assoc()) {
                $txt = trim(preg_replace('/<[^>]+>/', ' ', $row['mensaje']));
                $txt = preg_replace('/\s+/', ' ', $txt);
                if (mb_strlen($txt) > 200) $txt = mb_substr($txt, 0, 200) . '...';
                $lines[] = ($row['rol'] === 'user' ? 'Estudiante: ' : 'Tutor: ') . $txt;
            }
            return implode("\n", array_reverse($lines));
        } catch (Exception $e) { return ''; }
    }

    private function esAclaracion($m) {
        return (bool) preg_match('/\b(lo que ped[ií]|no otros?|no otras?|solo eso|ese libro|el mismo|no te ped[ií]|no quiero otros?|esencialmente|exactamente|el que ped[ií]|no esos|no esas)\b/iu', $m);
    }

    private function rescatarPeticionLibros($historial) {
        if ($historial === '') return '';
        if (preg_match_all('/Estudiante: (.+)/u', $historial, $m)) {
            foreach (array_reverse($m[1]) as $linea) {
                if (preg_match('/\b(libro|libros|pdf|manual|gu[ií]a|b[oó]tame|v[oó]tame)\b/iu', $linea)) return trim($linea);
            }
        }
        return '';
    }

    private function esPeticionExacta($q) {
        return (bool) preg_match('/\b(utp|uni|upn|usmp|ucv|ute|upc|udh|usil|tecsup|senati|wiener|cat[oó]lica|universidad)\b/iu', $q)
            || (bool) preg_match('/\b(el|un|ese) libro\b/iu', $q)
            || (bool) preg_match('/\b(materia|curso|carrera)\b/iu', $q);
    }

    private function buscarLibrosCompletos($q, $curso_id = 0, $exacto = false) {
        $qLimpio = $this->limpiarConsulta($q);
        if (mb_strlen($qLimpio) < 2) $qLimpio = $q;
        $tokens = $this->extraerTokens($qLimpio, 5);
        $html = '';
        $total = 0;

        $propios = $this->buscarLibrosPropios($qLimpio, $curso_id);
        if (!empty($propios)) {
            $html .= '🏫 <strong>De tu plataforma Devioz</strong> (PDF completo):<br><br>';
            foreach (array_slice($propios, 0, $exacto ? 2 : 5) as $i => $l) {
                $total++;
                $html .= '<strong>' . ($i + 1) . '. ' . htmlspecialchars($l['titulo']) . '</strong>' . (!empty($l['autor']) ? ' — ' . htmlspecialchars($l['autor']) : '') .
                         '<br><a href="' . htmlspecialchars($l['pdf_url']) . '" target="_blank">📄 Ver / descargar PDF completo</a><br><br>';
            }
        }

        $googleBooks = $this->buscarGoogleBooksAcademicos($qLimpio, $exacto, $tokens);
        if (!empty($googleBooks)) {
            $html .= '<br>📚 <strong>Libros académicos (Google Books)</strong>:<br><br>';
            foreach ($googleBooks as $i => $l) {
                $total++;
                $html .= '<strong>' . ($i + 1) . '. ' . htmlspecialchars($l['titulo']) . '</strong><br>';
                $html .= '<small>' . htmlspecialchars($l['autores']) . ' · ' . htmlspecialchars($l['editorial']) . ' ' . (!empty($l['anio']) ? '(' . $l['anio'] . ')' : '') . '</small><br>';
                $html .= '<a href="' . htmlspecialchars($l['url_pdf']) . '" target="_blank">📖 ' . htmlspecialchars($l['tipo']) . '</a><br><br>';
            }
        }

        if ($total === 0) {
            $html = $exacto
                ? '😔 No encontré <strong>el libro exacto</strong> que pediste ("' . htmlspecialchars($qLimpio) . '").<br><br>Puedo armarte un <strong>resumen completo del tema</strong>, <strong>explicarte los conceptos clave</strong> o <strong>un mini-simulacro</strong>. Dime cuál.'
                : '📚 No encontré libros académicos para "<em>' . htmlspecialchars($qLimpio) . '</em>".<br><br>¿Quieres que te explique el tema directamente?';
        } else {
            $html = '📚 <strong>' . $total . ($exacto ? ' libro encontrado' : ' libros académicos encontrados') . '</strong> para "<em>' . htmlspecialchars($qLimpio) . '</em>":<br><br>' . $html;
        }
        return ['html' => $html, 'total' => $total];
    }

    private function buscarGoogleBooksAcademicos($q, $exacto = false, $tokens = []) {
        $url = 'https://www.googleapis.com/books/v1/volumes?' . http_build_query(['q' => $q, 'maxResults' => 15, 'printType' => 'books', 'orderBy' => 'relevance', 'langRestrict' => 'es']);
        $json = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true]);
            $json = @json_decode(curl_exec($ch), true);
            curl_close($ch);
        } else {
            $json = @json_decode(@file_get_contents($url), true);
        }
        if (empty($json['items'])) return [];
        
        $out = [];
        $editorialesValidas = ['Pearson', 'McGraw-Hill', 'McGraw Hill', 'Springer', 'Elsevier', 'Oxford', 'Cambridge', 'MIT Press', 'Harvard', 'UNAM', 'Siglo XXI', 'Gedisa', 'Alianza', 'Planeta', 'Reverté', 'Limusa', 'Alfaomega', 'Cengage', 'Wiley', 'Addison-Wesley', 'Prentice'];
        
        foreach ($json['items'] as $item) {
            if (count($out) >= ($exacto ? 3 : 8)) break;
            $vi = $item['volumeInfo'] ?? [];
            $ai = $item['accessInfo'] ?? [];
            $titulo = $vi['title'] ?? '';
            if ($titulo === '' || $this->esBasura($titulo)) continue;
            if (mb_strlen($titulo) < 10) continue;
            
            $editorial = $vi['publisher'] ?? '';
            $anio = substr($vi['publishedDate'] ?? '', 0, 4);
            $esAcademico = false;
            foreach ($editorialesValidas as $ed) {
                if (stripos($editorial, $ed) !== false) { $esAcademico = true; break; }
            }
            $paginas = intval($vi['pageCount'] ?? 0);
            if (!$esAcademico && $paginas < 100) continue;
            
            $rank = $this->coincidenciasTitulo($titulo, $tokens);
            if ($exacto && $rank < 1) continue;
            
            $pdf = $ai['pdfDownloadLink'] ?? '';
            $web = $ai['webReaderLink'] ?? '';
            $pd = !empty($ai['publicDomain']);
            $pdfAv = !empty($ai['pdfIsAvailable']);
            
            if ($pdf !== '') { $tipo = 'PDF COMPLETO'; $link = $pdf; } 
            elseif ($pd && $web) { $tipo = 'LEER COMPLETO (gratis)'; $link = $web; } 
            elseif ($pdfAv && $web) { $tipo = 'VISTA PREVIA'; $link = $web; } 
            else continue;
            
            $out[] = ['titulo' => $titulo, 'autores' => implode(', ', array_slice($vi['authors'] ?? ['Autor no indicado'], 0, 3)), 'editorial' => $editorial ?: 'Editorial no indicada', 'anio' => $anio, 'url_pdf' => $link, 'tipo' => $tipo, 'rank' => $rank];
        }
        usort($out, function ($a, $b) { return ($b['rank'] ?? 0) <=> ($a['rank'] ?? 0); });
        return $out;
    }

    private function buscarLibrosPropios($q, $curso_id = 0) {
        $tokens = $this->extraerTokens($q, 4);
        $out = [];
        if (!count($tokens)) return $out;
        $conds = []; $tipos = ''; $vals = [];
        foreach ($tokens as $t) { $like = '%' . $t . '%'; $conds[] = '(l.titulo LIKE ? OR l.autor LIKE ? OR l.descripcion LIKE ?)'; $tipos .= 'sss'; array_push($vals, $like, $like, $like); }
        try {
            $stmt = $this->db->prepare("SELECT l.titulo, l.autor, CONCAT('/lms_prepa/uploads/libros/', l.pdf_url) AS pdf_url FROM libros l WHERE l.estado='activo' AND l.pdf_url IS NOT NULL AND l.pdf_url != '' AND (" . implode(' OR ', $conds) . ") LIMIT 5");
            $stmt->bind_param($tipos, ...$vals);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) $out[] = $row;
        } catch (Exception $e) { }
        if (count($out) < 3) {
            $conds = []; $tipos = ''; $vals = [];
            foreach ($tokens as $t) { $like = '%' . $t . '%'; $conds[] = '(c.nombre LIKE ? OR c.descripcion LIKE ? OR u.nombre LIKE ?)'; $tipos .= 'sss'; array_push($vals, $like, $like, $like); }
            try {
                $stmt = $this->db->prepare("SELECT c.nombre AS titulo, '' AS autor, c.pdf_url FROM cursos c LEFT JOIN universidades u ON u.id = c.universidad_id WHERE c.pdf_url IS NOT NULL AND c.pdf_url != '' AND (" . implode(' OR ', $conds) . ") LIMIT 5");
                $stmt->bind_param($tipos, ...$vals);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) $out[] = $row;
            } catch (Exception $e) { }
        }
        return $out;
    }

    private function coincidenciasTitulo($titulo, $tokens) {
        $t = mb_strtolower($titulo);
        $n = 0;
        foreach ($tokens as $tok) if (mb_strtolower($tok) !== '' && mb_strpos($t, mb_strtolower($tok)) !== false) $n++;
        return $n;
    }

    private function limpiarConsulta($q) {
        $q = preg_replace('/\b(yo|me|nos|dame|danos|b[oó]tame|v[oó]tame|mu[eé]strame|busca|b[uú]scame|necesito|quiero|consigue|env[ií]ame|m[aá]ndame|mandes|libro|libros|pdf|pdfs|documento|documentos|gu[ií]a|gu[ií]as|manual|manuales|material|bibliograf[ií]a|paper|papers|art[ií]culo|art[ií]culos|de|del|la|las|el|los|unas?|unos?|para|por|sobre|acerca|con|sin|y|o|u|en|que|mi|su|tal|solo|pido|ped[ií]|otros?|otras?|referencia|referencias|esencialmente|exactamente)\b/iu', ' ', $q);
        return trim(preg_replace('/\s+/', ' ', $q));
    }

    private function extraerTokens($q, $max = 5) {
        $palabras = preg_split('/\s+/', $q);
        $tokens = array_values(array_filter($palabras, function ($p) { return mb_strlen($p) >= 3; }));
        return array_slice($tokens, 0, $max);
    }

    private function esBasura($titulo) {
        $t = mb_strtolower($titulo);
        $basura = ['acreditacion','acreditación','informe','boletin','boletín','silabo','sílabo','prospecto','reglamento','estatuto','acta','resolucion','resolución','plan de estudios','programa','powerpoint','presentation','presentación','main.pdf','solicitud','manual de procedimientos','criterios de interpretacion'];
        foreach ($basura as $b) if (strpos($t, $b) !== false) return true;
        return false;
    }

    private function guardarEnTutorHistorial($usuario_id, $pregunta, $respuesta) {
        try {
            $titulo = mb_substr($pregunta, 0, 60);
            $stmt = $this->db->prepare("SELECT id FROM tutor_conversaciones WHERE usuario_id = ? AND updated_at >= NOW() - INTERVAL 2 HOUR ORDER BY updated_at DESC LIMIT 1");
            $stmt->bind_param('i', $usuario_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                $convId = $row['id'];
                $upd = $this->db->prepare("UPDATE tutor_conversaciones SET updated_at = NOW() WHERE id = ?");
                $upd->bind_param('i', $convId);
                $upd->execute();
            } else {
                $ins = $this->db->prepare("INSERT INTO tutor_conversaciones (usuario_id, titulo) VALUES (?, ?)");
                $ins->bind_param('is', $usuario_id, $titulo);
                $ins->execute();
                $convId = $this->db->insert_id;
            }
            $insU = $this->db->prepare("INSERT INTO tutor_mensajes (conversacion_id, rol, mensaje) VALUES (?, 'user', ?)");
            $insU->bind_param('is', $convId, $pregunta);
            $insU->execute();
            $insI = $this->db->prepare("INSERT INTO tutor_mensajes (conversacion_id, rol, mensaje) VALUES (?, 'ia', ?)");
            $insI->bind_param('is', $convId, $respuesta);
            $insI->execute();
        } catch (Exception $e) { error_log("TutorHistorial Error: " . $e->getMessage()); }
    }

    public function investigarUniversidades() {
        $tipo = $_POST['tipo'] ?? 'nacionales';
        $admin_id = intval($_POST['admin_id'] ?? 1);
        try {
            $registradas = [];
            $res = $this->db->query("SELECT nombre FROM universidades");
            while ($row = $res->fetch_assoc()) $registradas[] = $this->normalizarNombre($row['nombre']);
            $ddg = new DuckDuckGoSearch();
            $bus = $ddg->buscarUniversidades($tipo, 500);
            if (!$bus['ok']) { echo json_encode($bus, JSON_UNESCAPED_UNICODE); exit; }
            $disponibles = [];
            foreach ($bus['data'] as $u) {
                $clave = $this->normalizarNombre($u['nombre']);
                if (!in_array($clave, $registradas, true)) $disponibles[] = $u;
            }
            usort($disponibles, function ($a, $b) { return strcasecmp($a['nombre'], $b['nombre']); });
            $this->guardarHistorial($admin_id, "Investigar $tipo", count($disponibles) . ' disponibles', 'investigar');
            echo json_encode(['ok' => true, 'msg' => count($disponibles) . ' instituciones disponibles en internet (las ya registradas se ocultan)', 'total' => count($disponibles), 'data' => $disponibles], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("Investigar Error: " . $e->getMessage());
            echo json_encode(['ok' => false, 'msg' => 'Error al investigar: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    public function ejecutarAccion() {
        $raw = $_POST['accion'] ?? null;
        if (!$raw) { echo json_encode(['ok' => false, 'msg' => 'Sin acción']); exit; }
        $accion = is_string($raw) ? json_decode($raw, true) : $raw;
        $tipo = $accion['accion'] ?? '';
        $params = $accion['parametros'] ?? $accion;
        try {
            $res = match($tipo) {
                'crear_universidad' => $this->crearUniversidad($params),
                'crear_curso' => $this->crearCurso($params),
                'crear_estudiante' => $this->crearEstudiante($params),
                default => ['ok' => false, 'msg' => "Acción '$tipo' no soportada"]
            };
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("EjecutarAccion Error: " . $e->getMessage());
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    private function crearUniversidad($p) {
        $nombre = trim($p['nombre'] ?? '');
        $ciudad = trim($p['ciudad'] ?? 'Lima');
        $tipo = trim($p['tipo'] ?? 'nacional');
        $pais = trim($p['pais'] ?? 'Perú');
        if (!$nombre) return ['ok' => false, 'msg' => 'Falta nombre'];
        $chk = $this->db->prepare("SELECT id FROM universidades WHERE LOWER(nombre) = LOWER(?)");
        $chk->bind_param("s", $nombre);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) return ['ok' => false, 'msg' => "La universidad '$nombre' ya existe"];
        $stmt = $this->db->prepare("INSERT INTO universidades (nombre, pais, ciudad, tipo) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nombre, $pais, $ciudad, $tipo);
        if ($stmt->execute()) return ['ok' => true, 'msg' => "✅ $nombre registrada con éxito", 'id' => $this->db->insert_id];
        return ['ok' => false, 'msg' => 'Error al guardar en BD'];
    }

    private function crearCurso($p) {
        $nombre = trim($p['nombre'] ?? '');
        $descripcion = trim($p['descripcion'] ?? 'Curso creado por IA');
        $nivel = intval($p['nivel_id'] ?? 1);
        if (!$nombre) return ['ok' => false, 'msg' => 'Falta nombre'];
        $stmt = $this->db->prepare("INSERT INTO cursos (nombre, descripcion, nivel_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $nombre, $descripcion, $nivel);
        if ($stmt->execute()) return ['ok' => true, 'msg' => "✅ Curso '$nombre' creado", 'id' => $this->db->insert_id];
        return ['ok' => false, 'msg' => 'Error al guardar curso'];
    }

    private function crearEstudiante($p) {
        $nombre = trim($p['nombre'] ?? '');
        $correo = trim($p['correo'] ?? '');
        $password = $p['password'] ?? '123456';
        if (!$nombre || !$correo) return ['ok' => false, 'msg' => 'Nombre y correo requeridos'];
        $chk = $this->db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $chk->bind_param("s", $correo);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) return ['ok' => false, 'msg' => 'El correo ya está registrado'];
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO usuarios (nombre, email, password, rol, estado) VALUES (?, ?, ?, 'estudiante', 'activo')");
        $stmt->bind_param("sss", $nombre, $correo, $hash);
        if ($stmt->execute()) return ['ok' => true, 'msg' => "✅ Estudiante '$nombre' creado", 'id' => $this->db->insert_id];
        return ['ok' => false, 'msg' => 'Error al guardar estudiante'];
    }

    private function guardarHistorial($admin_id, $pregunta, $respuesta, $accion) {
        try {
            $stmt = $this->db->prepare("INSERT INTO historial_ia (admin_id, pregunta, respuesta_ia, accion_ejecutada) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $admin_id, $pregunta, $respuesta, $accion);
            $stmt->execute();
        } catch (Exception $e) { error_log("Historial Error: " . $e->getMessage()); }
    }

    private function normalizarNombre($n) {
        $n = mb_strtolower($n);
        $n = preg_replace('/\([^)]*\)/', '', $n);
        $n = str_replace(['universidad','nacional','de','del','la','el','pontificia','católica','catolica','privada','perú','peru'], ' ', $n);
        return preg_replace('/\s+/', ' ', trim($n));
    }
}

$controller = new IAController();
$accion = $_REQUEST['accion'] ?? 'chatLibre';

$metodoMap = [
    'chatLibre' => 'chatLibre',
    'buscar_web' => 'buscarWeb',
    'buscarWeb' => 'buscarWeb',
    'investigarUniversidades' => 'investigarUniversidades',
    'ejecutarAccion' => 'ejecutarAccion'
];

$metodo = $metodoMap[$accion] ?? null;
if ($metodo && method_exists($controller, $metodo)) {
    $controller->$metodo();
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => "Acción '$accion' no válida"], JSON_UNESCAPED_UNICODE);
}
?>