<?php

declare(strict_types=1);

namespace App\Modules\Settings\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\Sms\Services\SmsService;
use App\Modules\Whatsapp\Services\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Admin-facing key/value settings — everything that shouldn't require a
 * redeploy to change lives here (Resend API key, MTC SMS credentials,
 * mail from-address, AI features toggles, ...).
 *
 * Encrypted fields are returned as booleans (`has_value: true`) rather than
 * the plaintext — the UI never displays a decrypted secret. To rotate one,
 * the admin re-enters it; blank input means "leave the stored value alone".
 */
class SettingsController extends Controller
{
    /**
     * Groups the UI knows about, with the exact keys under each. Keeps the
     * server as the source of truth for which fields are editable — the
     * frontend just renders whatever this describes.
     *
     * @var array<string, array<int, array{key: string, label: string, encrypted?: bool, type?: string}>>
     */
    private const GROUPS = [
        'mail' => [
            ['key' => 'from_address', 'label' => 'عنوان المرسِل', 'type' => 'email'],
            ['key' => 'from_name', 'label' => 'اسم المرسِل'],
            ['key' => 'resend_key', 'label' => 'مفتاح Resend', 'encrypted' => true, 'type' => 'password'],
        ],
        'sms' => [
            ['key' => 'mtc_username', 'label' => 'اسم مستخدم MTC'],
            ['key' => 'mtc_password', 'label' => 'كلمة مرور MTC', 'encrypted' => true, 'type' => 'password'],
            ['key' => 'mtc_sender', 'label' => 'اسم المرسِل (Sender ID)'],
            ['key' => 'mtc_endpoint', 'label' => 'MTC endpoint URL', 'type' => 'url'],
            ['key' => 'mtc_fake', 'label' => 'وضع الاختبار (Fake)', 'type' => 'boolean'],
        ],
        'whatsapp' => [
            ['key' => 'access_token', 'label' => 'Access Token', 'encrypted' => true, 'type' => 'password'],
            ['key' => 'phone_number_id', 'label' => 'Phone Number ID'],
            ['key' => 'business_account_id', 'label' => 'Business Account ID'],
            ['key' => 'fake', 'label' => 'وضع الاختبار', 'type' => 'boolean'],
        ],
        'ai' => [
            ['key' => 'anthropic_api_key', 'label' => 'مفتاح Anthropic Claude', 'encrypted' => true, 'type' => 'password'],
            ['key' => 'anthropic_model', 'label' => 'موديل Claude', 'type' => 'text'],
        ],
        'push' => [
            ['key' => 'expo_access_token', 'label' => 'Expo Access Token (اختياري)', 'encrypted' => true, 'type' => 'password'],
            ['key' => 'fake', 'label' => 'وضع الاختبار', 'type' => 'boolean'],
        ],
    ];

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * GET /api/admin/settings — returns the schema + current values.
     */
    public function index(): JsonResponse
    {
        $data = [];
        foreach (self::GROUPS as $group => $fields) {
            $groupValues = $this->settings->getGroup($group);
            $renderedFields = [];
            foreach ($fields as $field) {
                $storedValue = $groupValues[$field['key']] ?? null;
                $isEncrypted = $field['encrypted'] ?? false;

                $renderedFields[] = [
                    'key' => $field['key'],
                    'label' => $field['label'],
                    'type' => $field['type'] ?? 'text',
                    'encrypted' => $isEncrypted,
                    // Never leak the plaintext of an encrypted field — just
                    // signal "there's a value here" so the UI can render
                    // "•••" and keep the input blank.
                    'has_value' => $storedValue !== null && $storedValue !== '',
                    'value' => $isEncrypted ? null : $storedValue,
                ];
            }
            $data[$group] = $renderedFields;
        }

        return response()->json(['data' => $data]);
    }

    /**
     * PUT /api/admin/settings — persist updates.
     *
     * Payload shape: `{ mail: { from_address: '...', ...}, sms: { ... } }`.
     * A blank string for an encrypted field means "leave stored value alone"
     * — the admin has to type it in fresh to rotate.
     */
    public function update(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'mail' => 'array',
            'mail.*' => 'nullable|string|max:500',
            'sms' => 'array',
            'sms.*' => 'nullable|string|max:500',
            'whatsapp' => 'array',
            'whatsapp.*' => 'nullable|string|max:500',
            'ai' => 'array',
            'ai.*' => 'nullable|string|max:500',
        ]);

        foreach (self::GROUPS as $group => $fields) {
            $groupInput = $payload[$group] ?? [];
            foreach ($fields as $field) {
                $key = "{$group}.{$field['key']}";
                $encrypted = $field['encrypted'] ?? false;

                if (! array_key_exists($field['key'], $groupInput)) {
                    continue; // client omitted the field entirely
                }

                $value = $groupInput[$field['key']];

                // Blank encrypted field = keep the stored one. This is the
                // one asymmetry between plaintext and secret handling.
                if ($encrypted && ($value === null || $value === '')) {
                    continue;
                }

                $this->settings->set(
                    key: $key,
                    value: $value !== null && $value !== '' ? (string) $value : null,
                    group: $group,
                    encrypt: $encrypted,
                );
            }
        }

        return $this->index();
    }

    /**
     * `POST /api/admin/settings/test/mail`
     * Body: { to: "email@example.com" }
     *
     * Fires a one-liner mail through the currently-configured transport
     * (Resend in prod, log in dev). Runs sync — this is a manual smoke
     * test, we WANT the caller to wait and see the outcome.
     */
    public function testMail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'email', 'max:200'],
        ]);

        try {
            Mail::raw(
                "هذه رسالة اختبارية من منصة TAQAT. إذا وصلتك، فالإعدادات صحيحة.\n\n— TAQAT",
                function ($message) use ($data) {
                    $message->to($data['to'])
                        ->subject('اختبار إعدادات البريد — TAQAT');
                },
            );
            return response()->json(['data' => ['ok' => true, 'message' => "تم إرسال بريد الاختبار إلى {$data['to']}"]]);
        } catch (Throwable $e) {
            Log::warning('[settings:test-mail] failed', ['error' => $e->getMessage(), 'to' => $data['to']]);
            return response()->json([
                'data' => ['ok' => false, 'error' => $e->getMessage()],
                'message' => 'تعذر إرسال البريد. تحقق من مفتاح Resend والعنوان.',
            ], 422);
        }
    }

    /**
     * `POST /api/admin/settings/test/sms`
     * Body: { to: "+9627XXXXXXXX" }
     *
     * Uses SmsService::sendNow so the send runs inline (not queued) —
     * the admin can immediately see success/failure. Reads config through
     * SettingsService via the container-resolved SmsGateway, so a fresh
     * cred change is respected without a restart.
     */
    public function testSms(Request $request, SmsService $sms): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:32'],
        ]);

        try {
            $result = $sms->sendNow(
                to: $data['to'],
                body: 'رسالة اختبارية من TAQAT — الإعدادات تعمل بشكل صحيح.',
            );

            if (! $result->success) {
                return response()->json([
                    'data' => [
                        'ok' => false,
                        'error' => $result->error,
                        'provider_message_id' => $result->provider_message_id,
                        'raw_response' => $result->raw_response,
                    ],
                    'message' => $result->error ?: 'تعذر إرسال الرسالة. تحقق من إعدادات MTC.',
                ], 422);
            }

            return response()->json(['data' => [
                'ok' => true,
                'message' => "تم إرسال رسالة الاختبار إلى {$data['to']}",
                'provider_message_id' => $result->provider_message_id,
                'raw_response' => $result->raw_response,
            ]]);
        } catch (Throwable $e) {
            Log::warning('[settings:test-sms] failed', ['error' => $e->getMessage(), 'to' => $data['to']]);
            return response()->json([
                'data' => ['ok' => false, 'error' => $e->getMessage()],
                'message' => 'تعذر إرسال الرسالة. تحقق من إعدادات MTC.',
            ], 422);
        }
    }

    /**
     * `POST /api/admin/settings/test/whatsapp`
     * Body: { to: "+9627XXXXXXXX" }
     *
     * Same shape as testSms above but through the Meta Cloud gateway.
     * The recipient must have messaged the business within the last 24h
     * (WhatsApp's "customer service window") for a free-form message
     * like this one to be delivered — otherwise Meta rejects it and the
     * error surfaces in the response.
     */
    public function testWhatsapp(Request $request, WhatsappService $wa): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:32'],
        ]);

        try {
            $result = $wa->sendNow(
                to: $data['to'],
                body: 'رسالة اختبارية من TAQAT عبر واتساب — الإعدادات تعمل بشكل صحيح.',
            );

            if (! $result->success) {
                return response()->json([
                    'data' => [
                        'ok' => false,
                        'error' => $result->error,
                        'provider_message_id' => $result->provider_message_id,
                        'raw_response' => $result->raw_response,
                    ],
                    'message' => $result->error ?: 'تعذر إرسال الرسالة. تحقق من إعدادات واتساب.',
                ], 422);
            }

            return response()->json(['data' => [
                'ok' => true,
                'message' => "تم إرسال رسالة واتساب اختبارية إلى {$data['to']}",
                'provider_message_id' => $result->provider_message_id,
                'raw_response' => $result->raw_response,
            ]]);
        } catch (Throwable $e) {
            Log::warning('[settings:test-whatsapp] failed', ['error' => $e->getMessage(), 'to' => $data['to']]);
            return response()->json([
                'data' => ['ok' => false, 'error' => $e->getMessage()],
                'message' => 'تعذر إرسال الرسالة. تحقق من إعدادات واتساب.',
            ], 422);
        }
    }
}
