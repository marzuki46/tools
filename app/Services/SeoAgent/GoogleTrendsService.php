<?php

namespace App\Services\SeoAgent;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleTrendsService
{
    protected string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

    public function analyze(string $keyword, string $geo = 'ID', string $time = 'today 1-y'): array
    {
        try {
            $data = $this->fetchTrends($keyword, $geo, $time);
            if ($data) {
                return $data;
            }
        } catch (Exception $e) {
            Log::warning('GoogleTrendsService: direct fetch failed', [
                'keyword' => $keyword,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $aiData = $this->analyzeWithAI($keyword, $geo);
            if ($aiData) {
                return $aiData;
            }
        } catch (Exception $e) {
            Log::warning('GoogleTrendsService: AI fallback failed', [
                'keyword' => $keyword,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->fallbackTrends($keyword, $geo, $time);
    }

    protected function fetchTrends(string $keyword, string $geo, string $time): ?array
    {
        $req = json_encode([
            'comparisonItem' => [
                ['keyword' => $keyword, 'geo' => $geo, 'time' => $time],
            ],
            'category' => 0,
            'property' => '',
        ]);

        $exploreUrl = 'https://trends.google.com/trends/api/explore?hl=en-US&tz=-420&req=' . urlencode($req);

        $resp = Http::withHeaders([
            'User-Agent' => $this->userAgent,
            'Accept' => 'application/json',
            'Referer' => 'https://trends.google.com/',
        ])->timeout(15)->get($exploreUrl);

        if ($resp->failed()) {
            throw new Exception('Explore API returned ' . $resp->status());
        }

        $body = $resp->body();
        $body = preg_replace('/^\)\]\}\'\s*/', '', $body);
        $explore = json_decode($body, true);

        if (!$explore || !isset($explore['widgets'])) {
            throw new Exception('Invalid explore response');
        }

        $widgets = $explore['widgets'];
        $token = $explore['token'] ?? '';

        $interestOverTime = [];
        $relatedQueries = ['top' => [], 'rising' => []];
        $geoData = [];
        $interestByRegion = [];

        foreach ($widgets as $widget) {
            if ($widget['id'] === 'TIMESERIES') {
                $interestOverTime = $this->fetchWidgetData($widget['token'], $token, $keyword, $geo, $time, 'multiline');
            } elseif ($widget['id'] === 'RELATED_QUERIES') {
                $rq = $this->fetchWidgetData($widget['token'], $token, $keyword, $geo, $time, 'relatedQueries');
                if ($rq) {
                    $relatedQueries = $rq;
                }
            } elseif ($widget['id'] === 'GEO_MAP') {
                $geoData = $this->fetchWidgetData($widget['token'], $token, $keyword, $geo, $time, 'geoMap');
            } elseif ($widget['id'] === 'GEO_MAP_0') {
                $interestByRegion = $this->fetchWidgetData($widget['token'], $token, $keyword, $geo, $time, 'geoMap');
            }
        }

        $trendData = $this->parseInterestOverTime($interestOverTime);
        $related = $this->parseRelatedQueries($relatedQueries);
        $regions = $this->parseGeoData(array_merge($geoData ?: [], $interestByRegion ?: []));

        return [
            'trend_direction' => $trendData['direction'],
            'trend_score' => $trendData['score'],
            'summary' => $trendData['summary'],
            'interest_over_time' => $trendData['timeline'],
            'related_topics' => $related['top'],
            'rising_queries' => $related['rising'],
            'related_questions' => [],
            'regions' => $regions,
            'prediction' => $trendData['prediction'],
            'source_data' => true,
        ];
    }

    protected function fetchWidgetData(string $widgetToken, string $reqToken, string $keyword, string $geo, string $time, string $type): ?array
    {
        $req = json_encode([
            'comparisonItem' => [
                ['keyword' => $keyword, 'geo' => $geo, 'time' => $time],
            ],
            'category' => 0,
            'property' => '',
        ]);

        $url = "https://trends.google.com/trends/api/widgetdata/{$type}?hl=en-US&tz=-420&req=" . urlencode($req) . '&token=' . urlencode($widgetToken);

        $resp = Http::withHeaders([
            'User-Agent' => $this->userAgent,
            'Accept' => 'application/json',
            'Referer' => 'https://trends.google.com/',
            'Cookie' => 'NID=511',
        ])->timeout(15)->get($url);

        if ($resp->failed()) {
            return null;
        }

        $body = $resp->body();
        $body = preg_replace('/^\)\]\}\'\s*/', '', $body);
        return json_decode($body, true) ?: null;
    }

    protected function parseInterestOverTime(?array $data): array
    {
        if (!$data || !isset($data['default']['timelineData'])) {
            return [
                'direction' => 'stabil',
                'score' => 50,
                'summary' => 'Data tren tidak tersedia.',
                'timeline' => [],
                'prediction' => 'Tidak dapat diprediksi.',
            ];
        }

        $timeline = [];
        $values = [];

        foreach ($data['default']['timelineData'] as $point) {
            $timeline[] = [
                'time' => date('M Y', $point['time'] / 1000),
                'value' => $point['value'][0] ?? 0,
            ];
            $values[] = $point['value'][0] ?? 0;
        }

        if (count($values) < 2) {
            return [
                'direction' => 'stabil',
                'score' => 50,
                'summary' => 'Data terlalu sedikit untuk analisis.',
                'timeline' => $timeline,
                'prediction' => 'Data tidak mencukupi.',
            ];
        }

        $avg = array_sum($values) / count($values);
        $firstHalf = array_slice($values, 0, (int)(count($values) / 2));
        $secondHalf = array_slice($values, (int)(count($values) / 2));
        $firstAvg = count($firstHalf) ? array_sum($firstHalf) / count($firstHalf) : 0;
        $secondAvg = count($secondHalf) ? array_sum($secondHalf) / count($secondHalf) : 0;

        $diff = $secondAvg - $firstAvg;
        $direction = 'stabil';
        if ($diff > 5) $direction = 'naik';
        elseif ($diff < -5) $direction = 'turun';

        $score = min(100, max(0, (int) $avg));
        $peak = max($values);
        $peakMonth = '';
        foreach ($timeline as $t) {
            if ($t['value'] == $peak) {
                $peakMonth = $t['time'];
                break;
            }
        }

        $summary = "Minat terhadap '{$this->truncate($keyword, 40)}' selama setahun terakhir: ";
        if ($direction === 'naik') {
            $summary .= "menunjukkan tren *naik*";
        } elseif ($direction === 'turun') {
            $summary .= "menunjukkan tren *turun*";
        } else {
            $summary .= "*stabil*";
        }
        $summary .= " (rata-rata {$score}/100). Puncak minat terjadi pada {$peakMonth} dengan skor {$peak}/100.";

        if ($score >= 70) {
            $summary .= " Keyword ini sangat populer.";
        } elseif ($score >= 40) {
            $summary .= " Popularitas sedang.";
        } else {
            $summary .= " Minat relatif rendah.";
        }

        $prediction = $direction === 'naik'
            ? "Kecenderungan naik, kemungkinan akan terus meningkat dalam 3-6 bulan ke depan."
            : ($direction === 'turun'
                ? "Kecenderungan menurun, mungkin perlu optimasi baru untuk meningkatkan minat."
                : "Relatif stabil, potensi pertumbuhan ada dengan strategi konten yang tepat.");

        return [
            'direction' => $direction,
            'score' => $score,
            'summary' => $summary,
            'timeline' => $timeline,
            'prediction' => $prediction,
        ];
    }

    protected function parseRelatedQueries(?array $data): array
    {
        $top = [];
        $rising = [];

        if (!$data) {
            return ['top' => [], 'rising' => []];
        }

        if (isset($data['default']['rankedList'])) {
            foreach ($data['default']['rankedList'] as $list) {
                foreach ($list['rankedKeyword'] ?? [] as $item) {
                    $label = $item['query'] ?? $item['value'] ?? '';
                    if (empty($label)) continue;

                    $isRising = ($item['link'] ?? '') === 'RISING' || ($item['link'] ?? '') === 'TOP_RISING';
                    if ($isRising) {
                        $rising[] = $label;
                    } else {
                        $top[] = $label;
                    }
                }
            }
        }

        return [
            'top' => array_slice($top, 0, 10),
            'rising' => array_slice($rising, 0, 10),
        ];
    }

    protected function parseGeoData(?array $data): array
    {
        if (!$data || !isset($data['default']['geoMapData'])) {
            return [];
        }

        $regions = [];
        foreach ($data['default']['geoMapData'] as $item) {
            $regions[] = [
                'region' => $item['geoName'] ?? $item['geoCode'] ?? '',
                'value' => $item['value'][0] ?? 0,
            ];
        }

        usort($regions, fn($a, $b) => $b['value'] <=> $a['value']);
        return array_slice($regions, 0, 5);
    }

    protected function analyzeWithAI(string $keyword, string $geo): ?array
    {
        $ai = Setting::aiConfig();
        $url = $ai['url'];
        $apiKey = $ai['api_key'];
        $model = $ai['chat_model'];

        if (!$url || !$apiKey) {
            return null;
        }

        $prompt = <<<PROMPT
Anda adalah asisten yang mengakses Google Trends secara real-time.

Cari data Google Trends untuk keyword berikut di Indonesia (geo=ID) dalam 12 bulan terakhir.

Keyword: {$keyword}

Berdasarkan data yang Anda temukan, berikan analisis dalam format JSON ini (HANYA JSON, tanpa markdown):

{
  "trend_direction": "naik|stabil|turun",
  "trend_score": 0-100,
  "summary": "Analisis tren 2-3 kalimat dengan data spesifik",
  "related_topics": ["Topik 1", "Topik 2", "Topik 3"],
  "related_questions": ["Pertanyaan 1?", "Pertanyaan 2?"],
  "regions": ["Wilayah 1", "Wilayah 2", "Wilayah 3"],
  "prediction": "Prediksi 1-2 kalimat"
}

PASTIKAN data akurat berdasarkan informasi terkini dari Google Trends.
PROMPT;

        try {
            $response = Http::timeout(60)->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->post("{$url}/v1/chat/completions", [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'Anda adalah analis Google Trends. Cari data terkini dan jawab dengan JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 2000,
            ]);

            if ($response->failed()) {
                return null;
            }

            $data = $response->json();
            $raw = $data['choices'][0]['message']['content'] ?? '';
            $raw = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $raw));
            $parsed = json_decode($raw, true);

            if (!is_array($parsed)) {
                return null;
            }

            $parsed['source_data'] = false;
            return $parsed;
        } catch (Exception $e) {
            Log::warning('GoogleTrendsService: AI fallback failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    protected function fallbackTrends(string $keyword, string $geo, string $time): array
    {
        $encoded = urlencode($keyword);
        $url = "https://trends.google.com/trends/explore?q={$encoded}&date=" . urlencode($time) . "&geo={$geo}";

        return [
            'trend_direction' => 'stabil',
            'trend_score' => 50,
            'summary' => "Data tren untuk '{$keyword}' tidak dapat diambil otomatis. Silakan cek langsung di Google Trends.\n🔗 {$url}",
            'interest_over_time' => [],
            'related_topics' => [],
            'rising_queries' => [],
            'related_questions' => [],
            'regions' => [],
            'prediction' => 'Gunakan link di atas untuk melihat data aktual.',
            'source_data' => false,
        ];
    }

    protected function truncate(string $str, int $len): string
    {
        return mb_strlen($str) > $len ? mb_substr($str, 0, $len - 3) . '...' : $str;
    }
}
