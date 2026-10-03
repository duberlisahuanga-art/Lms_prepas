<?php
/**
 * WebSearch PRO — Navegación real para el Tutor IA de Devioz
 * PASO 1: DetectorWeb decide automáticamente si buscar
 * PASO 2: leerPagina() lee artículos COMPLETOS (no solo snippets)
 * PASO 3: buscarTodo() combina Google + Wikipedia + repositorios
 */

/* ============================================================
 * PASO 1 — DETECTOR INTELIGENTE: ¿necesita buscar en internet?
 * ============================================================ */
class DetectorWeb {

    // Datos que CAMBIAN con el tiempo → SIEMPRE buscar
    const SIEMPRE = '/\b(hoy|ahora|actual|actualmente|actualizad|reciente|últim[oa]s?|este\s+(año|mes)|20\d{2}|precio|costo|tipo\s+de\s+cambio|d[oó]lar|euro|noticia|novedad|gan[oó]|resultados?\s+de|eleccion|campe[oó]n|ranking\s+actual|presidente|nuev[oa]s?\s+(ley|norma|modelo|versi[oó]n)|clima|tiempo\s+en|horario|pandemia|brote)\b/iu';

    // Conocimiento ESTABLE o razonamiento → NUNCA buscar (ahorra cuota)
    const NUNCA = '/\b(resuelve|calcula|derivad|integral|ecuaci[oó]n|simplifica|despeja|demuestra|ejercicio|problema\s+de\s+(mate|f[ií]sica|qu[ií]mica)|c[oó]digo|programa|funci[oó]n\s+en\s+(python|js|php|javascript)|chiste|consejo|opini[oó]n|expl[ií]came\s+(qu[eé]\s+es|el\s+concepto|la\s+teor[ií]a)|definici[oó]n\s+de|f[oó]rmula\s+de)\b/iu';

    public static function necesitaBusqueda($texto) {
        if (preg_match(self::NUNCA, $texto))  return false;  // es matemática/código/concepto → el modelo ya lo sabe
        if (preg_match(self::SIEMPRE, $texto)) return true;  // dato cambiante → buscar sí o sí
        // AMBIGUO: pregunta factual (quién/cuándo/dónde + verbo de hecho)
        return (bool) preg_match(
            '/\b(qui[eé]n|cu[aá]ndo|d[oó]nde|cu[aá]l|cu[aá]ntos?)\b[^.?!]*\b(fue|es|son|pas[oó]|ocurri[oó]|gan[oó]|muri[oó]|naci[oó]|existe|hay|vive|trabaja)\b/iu',
            $texto
        );
    }
}

/* ============================================================
 * PASO 2 y 3 — BÚSQUEDA MULTI-FUENTE + LECTURA COMPLETA
 * ============================================================ */
class WebSearch {

    const GOOGLE_API_KEY = 'AIzaSyREEMPLAZAME_1234567890_ejemplo';  // ← ⬅️ TU CLAVE REAL AQUÍ
    const GOOGLE_CX      = 'c6f475995f70f4f9e';

    private static $cacheLectura = [];   // no releer la misma URL en la misma petición

    public static function disponible() {
        $k = trim(self::GOOGLE_API_KEY);
        return $k !== '' && $k !== 'AIzaSyREEMPLAZAME_1234567890_ejemplo';
    }

    /* ---------- GOOGLE Custom Search ---------- */
    public static function buscarGoogle($query, $num = 4) {
        if (!self::disponible()) return [];
        $url = 'https://www.googleapis.com/customsearch/v1?' . http_build_query([
            'key' => self::GOOGLE_API_KEY,
            'cx'  => self::GOOGLE_CX,
            'q'   => $query,
            'num' => min(max((int)$num, 1), 10)
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($res === false || $code !== 200) { error_log("WebSearch Google HTTP $code"); return []; }

        $data = json_decode($res, true);
        $out  = [];
        foreach ($data['items'] ?? [] as $item) {
            $out[] = [
                'fuente'  => 'Google',
                'title'   => $item['title']   ?? '',
                'snippet' => $item['snippet'] ?? '',
                'link'    => $item['link']    ?? ''
            ];
        }
        return $out;
    }

    /* ---------- WIKIPEDIA (extractos limpios, sin API key) ---------- */
    public static function buscarWikipedia($query, $num = 2) {
        $url = 'https://es.wikipedia.org/w/api.php?' . http_build_query([
            'action' => 'query',
            'generator' => 'search',
            'gsrsearch' => $query,
            'gsrlimit' => min(max((int)$num,1), 5),
            'prop' => 'extracts',
            'exchars' => 900,
            'explaintext' => 1,
            'format' => 'json',
            'origin' => '*'
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => true]);
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res === false) return [];

        $data = json_decode($res, true);
        $out  = [];
        foreach ($data['query']['pages'] ?? [] as $p) {
            if (empty($p['extract'])) continue;
            $out[] = [
                'fuente'  => 'Wikipedia',
                'title'   => $p['title'] ?? '',
                'snippet' => mb_substr(trim($p['extract']), 0, 900),
                'link'    => 'https://es.wikipedia.org/?curid=' . ($p['pageid'] ?? '')
            ];
        }
        return $out;
    }

    /* ---------- PASO 3: COMBINA TODAS LAS FUENTES ---------- */
    public static function buscarTodo($query, $num = 4) {
        $google = self::buscarGoogle($query, $num);
        $wiki   = self::buscarWikipedia($query, 2);
        // Wikipedia primero (más confiable para temas académicos), luego Google
        return array_merge($wiki, $google);
    }

    /* ---------- PASO 2: LEER UNA PÁGINA COMPLETA (texto limpio) ---------- */
    public static function leerPagina($url, $max_chars = 1800) {
        if ($url === '' || isset(self::$cacheLectura[$url])) return self::$cacheLectura[$url] ?? '';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; DeviozTutorBot/1.0)'
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $texto = '';
        if ($html !== false && $code === 200) {
            $texto = self::extraerTexto($html, $max_chars);
        }
        self::$cacheLectura[$url] = $texto;
        return $texto;
    }

    /* Limpia el HTML y deja solo el contenido útil */
    public static function extraerTexto($html, $max_chars = 1800) {
        // 1) Quitar bloques de ruido
        $html = preg_replace('/<(script|style|noscript|svg|header|footer|nav|aside|form|iframe|button)[\s\S]*?<\/\1>/i', ' ', $html);
        // 2) Preferir el contenido principal si existe
        if (preg_match('/<article[\s\S]*?<\/article>/i', $html, $m))      $html = $m[0];
        elseif (preg_match('/<main[\s\S]*?<\/main>/i', $html, $m))        $html = $m[0];
        elseif (preg_match('/<div[^>]*(?:content|article|post)[^>]*>[\s\S]*?<\/div>/i', $html, $m)) $html = $m[0];
        // 3) Quitar tags, decodificar entidades, colapsar espacios
        $text = strip_tags($html, ' ');
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return mb_substr(trim($text), 0, $max_chars);
    }
}
?>