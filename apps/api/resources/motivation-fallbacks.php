<?php

declare(strict_types=1);

/**
 * Canned Arabic motivational lines served when the LLM path is
 * unavailable (missing API key, upstream outage, rate limit). Kept
 * generic on purpose — they don't reference numbers or personal
 * details because there was no user context to draw from.
 *
 * MotivationService rotates through this list deterministically by
 * weekday (dayOfWeek modulo count), so the fallback served on any
 * given day is stable across page loads while still shifting from
 * one day to the next.
 *
 * @return list<string>
 */

return [
    'ابدأ يومك بخطوة صغيرة وواضحة، والباقي سيرتّب نفسه بعدها.',
    'إنجاز اليوم لا يحتاج بطولة — يحتاج فقط أن تُنهي ما بدأت به.',
    'ما تراكم من جهد صغير أمس هو ما يفتح لك مهمة اليوم بثقة.',
    'ركّز على مهمة واحدة الآن، ثم انتقل. البساطة ستوفّر عليك نصف التعب.',
    'تقدّمك ليس مقاسًا بسرعتك، بل بثباتك على الحضور يومًا بعد يوم.',
    'اجعل هدفك اليوم أن تُقفل مهمة قديمة قبل أن تفتح مهمة جديدة.',
    'الوقت الذي تستثمره في التركيز الآن هو الذي يعطيك مساء هادئًا.',
];
