<?php
/**
 * 🌐 MOTOR TIPO NAVEGADOR (SOLO PERÚ) - con filtros de calidad
 */
class DuckDuckGoSearch {

    private $ciudades = ['Puerto Maldonado','Tingo María','Cerro de Pasco','Huancavelica','Chachapoyas','Moyobamba','Tarapoto','Huancayo','Ayacucho','Iquitos','Pucallpa','Chiclayo','Chimbote','Lambayeque','Barranca','Huacho','Huaral','Sullana','Chincha','Nazca','Callao','Arequipa','Trujillo','Cusco','Piura','Tacna','Cajamarca','Huaraz','Puno','Juliaca','Huánuco','Abancay','Tumbes','Moquegua','Bagua','Cañete','Ilo','Jaén','Ica','Lima'];

    private $catalogoInstitutos = [
        ['nombre'=>'SENATI','ciudad'=>'Lima'],
        ['nombre'=>'TECSUP','ciudad'=>'Lima'],
        ['nombre'=>'Instituto IDAT','ciudad'=>'Lima'],
        ['nombre'=>'Instituto Toulouse Lautrec','ciudad'=>'Lima'],
        ['nombre'=>'Instituto Cibertec','ciudad'=>'Lima'],
        ['nombre'=>'Instituto Certus','ciudad'=>'Lima'],
        ['nombre'=>'Instituto Superior Tecnológico Público José Pardo','ciudad'=>'Lima'],
        ['nombre'=>'Instituto Superior Tecnológico Monterrico','ciudad'=>'Lima'],
    ];

    // Palabras que indican que NO es una institución (rankings, listas, etc.)
    private $basura = ['clasificación','clasificacion','ranking','mejores','top ','lista de','anexo','wikipedia','categoría','categoria','mundial','latinoamérica','latinoamerica','qs ','times higher'];

    // El nombre DEBE empezar con una de estas palabras para ser válido
    private $prefijosValidos = '/^(universidad|pontificia|instituto|escuela|senati|tecsup|idat|cibertec|certus|toulouse)/iu';

    public function buscarUniversidades($tipo = 'nacionales', $limite = 200, $ciudad = null) {
        $esInstituto = (stripos($tipo, 'instituto') !== false);
        $esPublica   = (!$esInstituto && stripos($tipo, 'nacional') !== false);
        $tipoNorm    = $esInstituto ? 'instituto' : ($esPublica ? 'nacional' : 'particular');

        $resultados = []; $vistos = [];

        foreach ($this->wikipedia($esPublica, $esInstituto) as $r) {
            $k = $this->clave($r['nombre']);
            if ($k !== '' && !isset($vistos[$k])) { $vistos[$k] = true; $resultados[] = $r; }
        }
        foreach ($this->duckduckgo($esPublica, $esInstituto) as $r) {
            $k = $this->clave($r['nombre']);
            if ($k !== '' && !isset($vistos[$k])) { $vistos[$k] = true; $resultados[] = $r; }
        }
        if ($esInstituto) {
            foreach ($this->catalogoInstitutos as $c) {
                $k = $this->clave($c['nombre']);
                if (!isset($vistos[$k])) { $vistos[$k] = true; $resultados[] = ['nombre'=>$c['nombre'],'ciudad'=>$c['ciudad'],'url'=>'']; }
            }
        }

        foreach ($resultados as &$r) { $r['tipo'] = $tipoNorm; $r['fuente'] = 'internet'; }
        unset($r);

        if ($ciudad) {
            $resultados = array_values(array_filter($resultados, function ($r) use ($ciudad) { return stripos($r['ciudad'], $ciudad) !== false; }));
        }
        if (empty($resultados)) {
            return ['ok'=>false,'total'=>0,'data'=>[],'msg'=>'No se pudo consultar internet en este momento. Intenta de nuevo.'];
        }
        return ['ok'=>true,'total'=>count($resultados),'data'=>array_slice($resultados,0,$limite)];
    }

    /** Descarta rankings, listas y páginas que no son instituciones */
    private function esNombreValido($nombre) {
        if (!preg_match($this->prefijosValidos, $nombre)) return false;
        $n = mb_strtolower($nombre);
        foreach ($this->basura as $b) {
            if (mb_strpos($n, $b) !== false) return false;
        }
        return true;
    }

    private function wikipedia($esPublica, $esInstituto) {
        $pagina = $esInstituto ? 'Anexo:Institutos de educación superior del Perú' : 'Anexo:Universidades del Perú';
        $url = 'https://es.wikipedia.org/w/api.php?action=parse&page=' . urlencode($pagina) . '&prop=wikitext&format=json&formatversion=2';
        $raw = $this->get($url, false);
        if (!$raw) return [];
        $data = json_decode($raw, true);
        $texto = $data['parse']['wikitext'] ?? '';
        if (!is_string($texto) || $texto === '') return [];

        $salida = [];
        if (preg_match_all('/\[\[\s*([^\]|#]+)(?:\|[^\]]*)?\]\]/u', $texto, $m)) {
            foreach ($m[1] as $nombre) {
                $nombre = trim($nombre);
                if (!$this->esNombreValido($nombre)) continue;
                if ($esInstituto) {
                    if (mb_stripos($nombre, 'instituto') === false) continue;
                } else {
                    if (mb_stripos($nombre, 'universidad') === false && mb_stripos($nombre, 'pontificia') === false) continue;
                    $esNac = (mb_stripos($nombre, 'nacional') !== false);
                    if ($esPublica !== $esNac) continue;
                }
                $salida[] = ['nombre'=>$nombre,'ciudad'=>$this->detectarCiudad($nombre),'url'=>'https://es.wikipedia.org/wiki/'.rawurlencode(str_replace(' ','_',$nombre))];
            }
        }
        return $salida;
    }

    private function duckduckgo($esPublica, $esInstituto) {
        if ($esInstituto)      $qs = ['institutos de educación superior tecnológica del Perú', 'institutos superiores tecnológicos Perú'];
        elseif ($esPublica)    $qs = ['universidades nacionales públicas del Perú sede'];
        else                   $qs = ['universidades privadas particulares del Perú sede'];

        $salida = [];
        foreach ($qs as $q) {
            $html = $this->get('https://html.duckduckgo.com/html/?q=' . urlencode($q), true);
            if (!$html) continue;
            if (preg_match_all('/class="result__a"[^>]*>(.*?)<\/a>/s', $html, $m)) {
                foreach ($m[1] as $titulo) {
                    $titulo = trim(strip_tags(html_entity_decode($titulo, ENT_QUOTES, 'UTF-8')));
                    $nombre = trim(preg_replace('/\s*[-|–:]\s*.*$/u', '', $titulo));
                    if (!$this->esNombreValido($nombre)) continue;
                    $salida[] = ['nombre'=>$nombre,'ciudad'=>$this->detectarCiudad($nombre.' '.$titulo),'url'=>''];
                }
            }
        }
        return $salida;
    }

    private function get($url, $comoNavegador = false) {
        $ch = curl_init($url);
        $h = ['Accept: text/html,application/json;q=0.9,*/*;q=0.8'];
        $h[] = $comoNavegador
            ? 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
            : 'User-Agent: DeviozAcademia/1.0 (proyecto educativo)';
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>15, CURLOPT_HTTPHEADER=>$h, CURLOPT_SSL_VERIFYPEER=>false]);
        $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return ($code === 200 && $r) ? $r : null;
    }

    /** Detecta ciudad como PALABRA COMPLETA (ya no matchea "Católica" → "Ica") */
    private function detectarCiudad($texto) {
        foreach ($this->ciudades as $c) {
            if (preg_match('/(?<![\p{L}])' . preg_quote($c, '/') . '(?![\p{L}])/iu', $texto)) return $c;
        }
        return 'Perú';
    }

    private function clave($nombre) {
        $n = mb_strtolower($nombre);
        $n = preg_replace('/\([^)]*\)/u', '', $n);
        $n = str_replace(['universidad','nacional','instituto','de','del','la','el','pontificia','católica','catolica','privada','perú','peru'], ' ', $n);
        return preg_replace('/\s+/u', ' ', trim($n));
    }
}
?>