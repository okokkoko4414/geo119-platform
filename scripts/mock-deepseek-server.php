<?php
/**
 * Mock DeepSeek API server for translation pipeline E2E testing.
 *
 * Start: php -S localhost:8081 scripts/mock-deepseek-server.php
 *
 * Returns plausible mock translations so the full pipeline (TranslationManager
 * -> TranslateStringJob -> QualityGate -> TranslationCache) can run end-to-end
 * without a real LLM backend.
 */

// ── Language-specific mock translations ──────────────────────────────────
$mockTranslations = [
    'vi' => [
        'GEO119' => 'GEO119',
        'Home' => 'Trang chủ',
        'Submit' => 'Gửi',
        'Cancel' => 'Hủy',
        'Search...' => 'Tìm kiếm...',
        'Loading...' => 'Đang tải...',
        'Payment' => 'Thanh toán',
        'No results found' => 'Không tìm thấy kết quả',
        'Previous' => 'Trước',
        'Next' => 'Tiếp',
        'Contact' => 'Liên hệ',
        'Total' => 'Tổng cộng',
        'Save' => 'Lưu',
        'Delete' => 'Xóa',
        'Edit' => 'Chỉnh sửa',
        'View' => 'Xem',
        'Download' => 'Tải xuống',
        'Upload' => 'Tải lên',
        'Retry' => 'Thử lại',
    ],
    'ja' => [
        'GEO119' => 'GEO119',
        'Home' => 'ホーム',
        'Submit' => '送信',
        'Cancel' => 'キャンセル',
        'Search...' => '検索...',
        'Loading...' => '読み込み中...',
        'Payment' => '支払い',
        'No results found' => '結果が見つかりません',
        'Previous' => '前へ',
        'Next' => '次へ',
        'Contact' => 'お問い合わせ',
        'Total' => '合計',
        'Save' => '保存',
        'Delete' => '削除',
        'Edit' => '編集',
    ],
    'ko' => [
        'GEO119' => 'GEO119',
        'Home' => '홈',
        'Submit' => '제출',
        'Cancel' => '취소',
        'Search...' => '검색...',
        'Loading...' => '로딩 중...',
        'Payment' => '결제',
        'No results found' => '결과를 찾을 수 없습니다',
        'Previous' => '이전',
        'Next' => '다음',
        'Contact' => '연락처',
    ],
    'fr' => [
        'GEO119' => 'GEO119',
        'Home' => 'Accueil',
        'Submit' => 'Soumettre',
        'Cancel' => 'Annuler',
        'Search...' => 'Rechercher...',
        'Loading...' => 'Chargement...',
        'Payment' => 'Paiement',
        'No results found' => 'Aucun résultat trouvé',
        'Previous' => 'Précédent',
        'Next' => 'Suivant',
        'Contact' => 'Contact',
    ],
    'de' => [
        'GEO119' => 'GEO119',
        'Home' => 'Startseite',
        'Submit' => 'Absenden',
        'Cancel' => 'Abbrechen',
        'Search...' => 'Suchen...',
        'Loading...' => 'Laden...',
        'Payment' => 'Zahlung',
        'No results found' => 'Keine Ergebnisse gefunden',
        'Previous' => 'Zurück',
        'Next' => 'Weiter',
        'Contact' => 'Kontakt',
    ],
    'es' => [
        'GEO119' => 'GEO119',
        'Home' => 'Inicio',
        'Submit' => 'Enviar',
        'Cancel' => 'Cancelar',
        'Search...' => 'Buscar...',
        'Loading...' => 'Cargando...',
        'Payment' => 'Pago',
        'No results found' => 'Sin resultados',
        'Contact' => 'Contacto',
    ],
    'pt' => [
        'GEO119' => 'GEO119',
        'Home' => 'Início',
        'Submit' => 'Enviar',
        'Cancel' => 'Cancelar',
        'Search...' => 'Pesquisar...',
        'Loading...' => 'Carregando...',
        'Payment' => 'Pagamento',
        'No results found' => 'Nenhum resultado encontrado',
        'Contact' => 'Contato',
    ],
];

// Generic fallback: add "[{lang}] " prefix to mark it as a mock translation
function generateMockTranslation(string $text, string $targetLang): string {
    global $mockTranslations;

    // Check if we have a pre-crafted translation
    if (isset($mockTranslations[$targetLang])) {
        foreach ($mockTranslations[$targetLang] as $en => $translated) {
            if (stripos($text, $en) !== false) {
                return str_ireplace($en, $translated, $text);
            }
        }
    }

    // For any unmatched text, wrap with lang prefix to make it identifiable
    return "[{$targetLang}] {$text}";
}

// ── Main request handler ──────────────────────────────────────────────────

// CORS headers for local dev
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Health check
if ($_SERVER['REQUEST_URI'] === '/health' || $_SERVER['REQUEST_URI'] === '/') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'service' => 'mock-deepseek']);
    exit;
}

// Handle chat completions
if ($_SERVER['REQUEST_URI'] === '/v1/chat/completions' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    if (!$body || !isset($body['messages'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }

    // Determine what kind of request this is from the system prompt
    $systemPrompt = '';
    $userMessage = '';
    foreach ($body['messages'] as $msg) {
        if ($msg['role'] === 'system') {
            $systemPrompt = $msg['content'];
        } elseif ($msg['role'] === 'user') {
            $userMessage = $msg['content'];
        }
    }

    $combinedPrompt = $systemPrompt . ' ' . $userMessage;
    $isTranslation = str_contains($systemPrompt, 'translator') || str_contains($systemPrompt, 'Translate');
    $isQualityScore = str_contains($combinedPrompt, 'Rate the quality of this translation')
                      || str_contains($systemPrompt, 'evaluator')
                      || str_contains($systemPrompt, 'COMET')
                      || str_contains($systemPrompt, 'translation quality');

    if ($isTranslation) {
        // Extract target language and text from user message
        // Format: "Target language code: {code}\n\nText to translate:\n{text}"
        $targetLang = 'en';
        if (preg_match('/Target language code:\s*(\w+)/', $userMessage, $m)) {
            $targetLang = $m[1];
        }

        $text = '';
        if (preg_match('/Text to translate:\s*\n(.+)$/s', $userMessage, $m)) {
            $text = trim($m[1]);
        }

        $translated = generateMockTranslation($text, $targetLang);

        $response = [
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => $translated,
                    ],
                ],
            ],
            'model' => 'mock-deepseek',
            'usage' => [
                'prompt_tokens' => (int)(strlen($userMessage) / 4),
                'completion_tokens' => (int)(strlen($translated) / 4),
                'total_tokens' => (int)((strlen($userMessage) + strlen($translated)) / 4),
            ],
        ];
    } elseif ($isQualityScore) {
        // Return just a number for quality scoring (QualityGate extracts via regex)
        $response = [
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => '0.90',
                    ],
                ],
            ],
            'model' => 'mock-deepseek',
            'usage' => [
                'prompt_tokens' => (int)(strlen($userMessage) / 4),
                'completion_tokens' => 3,
                'total_tokens' => (int)(strlen($userMessage) / 4) + 3,
            ],
        ];
    } else {
        $response = [
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Mock response',
                    ],
                ],
            ],
            'model' => 'mock-deepseek',
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
                'total_tokens' => 15,
            ],
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// 404 for everything else
http_response_code(404);
echo json_encode(['error' => 'Not found']);
