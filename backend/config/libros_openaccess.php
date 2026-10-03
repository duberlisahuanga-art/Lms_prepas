<?php
/**
 * 📚 BUSCADOR DE PDFs REALES (navega Google + DuckDuckGo + OpenAlex)
 * - Devuelve links directos a PDFs públicos indexados
 * - PRIORIZA repositorios académicos (.edu, .edu.pe, CONCYTEC, SciELO...)
 * - EXCLUYE sitios de piratería (LibGen, Z-Library, Sci-Hub, PDFDrive...)
 */
class LibrosOpenAccess {

    private $googleApiKey = 'AIzaSyBO8XLgNlWO622CX1FQeyxwQzrSBLmR1wA';
    private $searchEngineId = 'c6f475995f70f4f9e';

    private $piratas = ['libgen','z-library','zlibrary','z-lib','sci-hub','scihub','pdfdrive','epublibre','1lib','annas-archive','oceanofpdf','bookfi','booksc','b-ok','freepdfbook','singlekey'];
    private $academicos = ['.edu', '.edu.pe', '.gob.pe', '.ac.', 'repositorio', 'concytec', 'alicia', 'sunedu', 'scielo', 'redalyc', 'dialnet', 'doab', 'openalex', 'unmsm', 'uni.edu', 'pucp', 'upch', 'unitru', 'unsa'];

    public function buscar($query, $limite = 10) {
        $resultados = [];
        $vistos = [];

        $agregar = function ($r) use (&$resultados, &$vistos) {
            if (!$r['url_pdf']) return;
            $dom = $this->dominio($r['url_pdf']);
            foreach ($this->piratas as $p) { if (stripos($dom, $p) !== false) return; }
            $k = $this->clave($r['titulo'] . $dom);
            if (isset($vistos[$k])) return;
            $vistos[$k] = true;
            $r['dominio'] = $dom;
            $r['puntaje'] = $this->puntaje($dom);
            $resultados[] = $r;
        };

        foreach ($this->googlePDFs($query) as $r) $agregar($r);
        foreach ($this->duckPDFs($query) as $r) $agregar($r);
        foreach ($this->openAlex($query) as $r) $agregar($r);
        foreach ($this->crossRef($query) as $r) $agregar($r);

        usort($resultados, function ($a, $b) { return $b['puntaje'] <=> $a['puntaje']; });

        if (empty($resultados)) {
            return ['ok'=>false,'total'=>0,'data'=>[],
                    'msg'=>'No encontré PDFs públicos para esa búsqueda. Prueba con el título exacto o el nombre de la universidad.'];
        }
        return ['ok'=>true,'total'=>count($resultados),'data'=>array_slice($resultados,0,$limite)];
    }

    private function googlePDFs($query) {
        $url = "https://www.googleapis.com/customsearch/v1?key={$this->googleApiKey}&cx={$this->searchEngineId}"
             . "&q=" . urlencode($query . ' filetype:pdf') . "&fileType=pdf&num=10";
        $raw = $this->get($url);
        if (!$raw) return [];
        $data = json_decode($raw, true);
        $salida = [];
        foreach ($data['items'] ?? [] as $it) {
            $link = $it['link'] ?? '';
            $mime = $it['mime'] ?? '';
            if (stripos($mime, 'pdf') === false && stripos($link, '.pdf') === false) continue;
            $salida[] = ['titulo'=>$it['title'] ?? 'Documento PDF','autores'=>'','anio'=>'','fuente'=>$it['displayLink'] ?? 'Google','url_pdf'=>$link,'tipo'=>'pdf'];
        }
        return $salida;
    }

    private function duckPDFs($query) {
        $html = $this->get('https://html.duckduckgo.com/html/?q=' . urlencode($query . ' filetype:pdf'), true);
        if (!$html) return [];
        $salida = [];
        if (preg_match_all('/<a[^>]*class="result__a"[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/s', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $href = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
                $titulo = trim(strip_tags(html_entity_decode($match[2], ENT_QUOTES, 'UTF-8')));
                if (stripos($href, 'uddg=') !== false) {
                    $parts = parse_url($href);
                    parse_str($parts['query'] ?? '', $qs);
                    $href = $qs['uddg'] ?? $href;
                }
                if (stripos($href, '.pdf') === false) continue;
                $salida[] = ['titulo'=>$titulo ?: 'Documento PDF','autores'=>'','anio'=>'','fuente'=>$this->dominio($href),'url_pdf'=>$href,'tipo'=>'pdf'];
            }
        }
        return $salida;
    }

    private function openAlex($query) {
        $url = 'https://api.openalex.org/works?search=' . urlencode($query)
             . '&filter=open_access.is_oa:true,types:book|types:book_chapter|types:dissertation'
             . '&per-page=8&mailto=admin@devioz.pe';
        $raw = $this->get($url);
        if (!$raw) return [];
        $data = json_decode($raw, true);
        $salida = [];
        foreach ($data['results'] ?? [] as $w) {
            $pdf = $w['best_oa_location']['pdf_url'] ?? ($w['open_access']['oa_url'] ?? '');
            if (!$pdf) continue;
            $autores = [];
            foreach (($w['authorships'] ?? []) as $a) $autores[] = $a['author']['display_name'] ?? '';
            $salida[] = ['titulo'=>$w['display_name'] ?? 'Sin título','autores'=>implode(', ', array_slice(array_filter($autores),0,3)),'anio'=>$w['publication_year'] ?? '','fuente'=>$w['primary_location']['source']['display_name'] ?? 'OpenAlex','url_pdf'=>$pdf,'tipo'=>$w['type'] ?? 'book'];
        }
        return $salida;
    }

    private function crossRef($query) {
        $url = 'https://api.crossref.org/works?query=' . urlencode($query)
             . '&filter=type:book,has-full-text:true&rows=6';
        $raw = $this->get($url);
        if (!$raw) return [];
        $data = json_decode($raw, true);
        $salida = [];
        foreach ($data['message']['items'] ?? [] as $w) {
            $pdf = '';
            foreach ($w['link'] ?? [] as $l) { if (stripos($l['content-type'] ?? '', 'pdf') !== false) { $pdf = $l['URL']; break; } }
            if (!$pdf) continue;
            $autores = [];
            foreach (($w['author'] ?? []) as $a) $autores[] = trim(($a['given'] ?? '') . ' ' . ($a['family'] ?? ''));
            $salida[] = ['titulo'=>$w['title'][0] ?? 'Sin título','autores'=>implode(', ', array_slice(array_filter($autores),0,3)),'anio'=>$w['published']['date-parts'][0][0] ?? '','fuente'=>$w['publisher'] ?? 'CrossRef','url_pdf'=>$pdf,'tipo'=>'book'];
        }
        return $salida;
    }

    private function puntaje($dom) {
        foreach ($this->academicos as $a) { if (stripos($dom, $a) !== false) return 3; }
        if (stripos($dom, '.org') !== false) return 2;
        return 1;
    }
    private function dominio($url) { $h = parse_url($url, PHP_URL_HOST); return $h ? strtolower($h) : ''; }
    private function get($url, $comoNavegador = false) {
        $ch = curl_init($url);
        $h = ['Accept: application/json,text/html;q=0.9,*/*;q=0.8'];
        $h[] = $comoNavegador
            ? 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
            : 'User-Agent: DeviozAcademia/1.0 (mailto:admin@devioz.pe)';
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>15, CURLOPT_HTTPHEADER=>$h, CURLOPT_SSL_VERIFYPEER=>false]);
        $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return ($code === 200 && $r) ? $r : null;
    }
    private function clave($t) { return preg_replace('/\s+/u', ' ', trim(mb_strtolower($t))); }
}
?>