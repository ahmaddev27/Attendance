<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\Candidate;
use App\Models\CandidateImportJob;
use App\Models\JobRequirement;
use App\Models\User;
use App\Modules\Recruitment\Events\CsvImportCompleted;
use App\Modules\Recruitment\Jobs\ProcessCandidateCsvImport;
use App\Modules\Recruitment\Repositories\CandidateRepository;
use App\Shared\Enums\CandidateImportStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Owns the CSV/XLSX candidate-import pipeline end to end:
 * ─ `parse()`   — pure validation pass, used by dry-run AND by the worker
 * ─ `queue()`   — stores the file + mints the tracker row + dispatches
 * ─ `process()` — called by the Queue worker; dedup + attach-or-create
 *
 * The parse pass is deliberately a dry run with NO DB writes so the UI
 * can show the uploader a preview + error list before they commit. The
 * worker reuses the same parse pass so the "errors" the uploader sees
 * at dry-run time match what the row-level import actually enforces.
 *
 * Dedup contract (plan §9.3, Q4 locked):
 *   1. email (case-insensitive) OR phone (as-stored) match → reuse the
 *      existing candidate silently.
 *   2. no match → create a fresh row with `source = 'csv_import'`.
 *   3. candidate already attached to this job → skip with a warning
 *      (the UNIQUE index on candidate_applications is the backstop).
 */
class CandidateImportService
{
    /**
     * The ONLY headers the import understands. The template endpoint
     * emits this exact list; anything else in the uploaded file is
     * ignored silently so Excel's auto-added "Column1" junk doesn't
     * explode a legit import.
     *
     * @var list<string>
     */
    public const TEMPLATE_HEADERS = [
        'full_name',
        'email',
        'phone',
        'country',
        'city',
        'linkedin_url',
        'headline',
        'years_of_experience',
        'current_title',
        'current_company',
        'expected_salary_min',
        'expected_salary_max',
        'salary_currency',
        'availability',
        'skills',
        'languages',
        'notes',
    ];

    private const STRING_COLUMNS = ['phone', 'country', 'city', 'headline', 'current_title', 'current_company', 'notes'];

    private const MAX_ROWS = 2000;

    private const CHUNK_SIZE = 100;

    private const PREVIEW_SIZE = 10;

    /**
     * Hard cap on the errors array so a 2k-row garbage upload can't
     * blow the JSON column or the response payload. The counters still
     * reflect reality — only the detailed messages are capped.
     */
    private const MAX_ERROR_ENTRIES = 500;

    private const STORAGE_DIRECTORY = 'recruitment/imports';

    public function __construct(
        private readonly CandidateRepository $candidates,
        private readonly CandidateApplicationService $applications,
        private readonly RecruitmentNumberGenerator $numbers,
    ) {}

    /**
     * Validate every row without touching the database. Returns the
     * same shape the status endpoint returns for a finished job, plus a
     * `preview` the dry-run UI uses to show "this is what row 1-10 will
     * look like after dedup / normalisation".
     *
     * @return array{rows_parsed:int,errors:list<array{row:int,field:string,message:string}>,preview:list<array<string,mixed>>}
     */
    public function parse(UploadedFile|string $file): array
    {
        $rows = $this->readRows($file);

        $errors = [];
        $preview = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 for header row, +1 to be 1-based.
            $normalised = $this->normaliseRow($row);
            $rowErrors = $this->validateRow($rowNumber, $normalised);

            if ($rowErrors !== []) {
                array_push($errors, ...$rowErrors);
            }

            if (count($preview) < self::PREVIEW_SIZE) {
                $preview[] = $normalised + ['__row__' => $rowNumber];
            }
        }

        return [
            'rows_parsed' => count($rows),
            'errors' => array_slice($errors, 0, self::MAX_ERROR_ENTRIES),
            'preview' => $preview,
        ];
    }

    /**
     * Persist the file, mint the tracker row (Pending), and hand off to
     * the worker. Returns the tracker so the controller can respond
     * 201 with the row id the client polls on.
     */
    public function queue(JobRequirement $job, UploadedFile $file, User $uploader): CandidateImportJob
    {
        $originalName = $file->getClientOriginalName() ?: 'import.csv';
        $storedName = Str::uuid()->toString().'.'.($file->getClientOriginalExtension() ?: 'csv');
        $storagePath = Storage::disk('local')->putFileAs(
            self::STORAGE_DIRECTORY,
            $file,
            $storedName,
        );

        $importJob = DB::transaction(fn () => CandidateImportJob::create([
            'job_requirement_id' => $job->id,
            'uploaded_by_user_id' => $uploader->id,
            'uploaded_filename' => $originalName,
            'storage_path' => $storagePath,
            'status' => CandidateImportStatus::Pending->value,
        ]));

        ProcessCandidateCsvImport::dispatch($importJob->id);

        return $importJob;
    }

    /**
     * Called by the Queue worker. Marks the row Processing, walks the
     * file in chunks of 100, applies the dedup rules, attaches via
     * CandidateApplicationService (so the UNIQUE guard + CandidateApplied
     * event stay centralised), and flips the row to a terminal status.
     */
    public function process(CandidateImportJob $importJob): void
    {
        $uploader = $importJob->uploadedByUser;
        $job = $importJob->jobRequirement;

        $importJob->fill([
            'status' => CandidateImportStatus::Processing->value,
            'started_at' => now(),
        ])->save();

        try {
            $diskPath = Storage::disk('local')->path($importJob->storage_path);
            $rows = $this->readRows($diskPath);

            $counters = [
                'total_rows' => count($rows),
                'created_candidates' => 0,
                'reused_candidates' => 0,
                'created_applications' => 0,
                'skipped_duplicates' => 0,
            ];
            $errors = [];

            foreach (array_chunk($rows, self::CHUNK_SIZE, preserve_keys: true) as $chunk) {
                $this->processChunk($chunk, $job, $uploader, $counters, $errors);
            }

            $importJob->fill($counters + [
                'status' => CandidateImportStatus::CompletedWithErrors->value,
                'errors' => array_slice($errors, 0, self::MAX_ERROR_ENTRIES),
                'completed_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $importJob->fill([
                'status' => CandidateImportStatus::Failed->value,
                'errors' => [
                    ['row' => 0, 'field' => '__file__', 'message' => $e->getMessage()],
                ],
                'completed_at' => now(),
            ])->save();
        }

        CsvImportCompleted::dispatch($importJob->fresh() ?? $importJob, $uploader);
    }

    /**
     * @param  array<int, array<string, mixed>>  $chunk
     * @param  array<string, int>  $counters
     * @param  list<array{row:int,field:string,message:string}>  $errors
     */
    private function processChunk(
        array $chunk,
        JobRequirement $job,
        User $uploader,
        array &$counters,
        array &$errors,
    ): void {
        foreach ($chunk as $index => $row) {
            $rowNumber = $index + 2;
            $normalised = $this->normaliseRow($row);
            $rowErrors = $this->validateRow($rowNumber, $normalised);

            if ($rowErrors !== []) {
                array_push($errors, ...$rowErrors);

                continue;
            }

            try {
                $outcome = $this->importRow($normalised, $job, $uploader);
            } catch (Throwable $e) {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => '__row__',
                    'message' => $e->getMessage(),
                ];

                continue;
            }

            if ($outcome['candidate_created']) {
                $counters['created_candidates']++;
            } else {
                $counters['reused_candidates']++;
            }

            if ($outcome['application_created']) {
                $counters['created_applications']++;
            } else {
                $counters['skipped_duplicates']++;
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'candidate_id',
                    'message' => 'هذا المرشّح متقدّم على هذه الوظيفة بالفعل.',
                ];
            }
        }
    }

    /**
     * Dedup + attach for ONE row. Candidate reuse is silent per Q4;
     * duplicate application returns `application_created = false` and
     * the caller logs a warning (not an error row — the plan is clear
     * that duplicate application is skipped, not rejected).
     *
     * @param  array<string, mixed>  $row
     * @return array{candidate:Candidate,candidate_created:bool,application_created:bool}
     */
    private function importRow(array $row, JobRequirement $job, User $uploader): array
    {
        $existing = $this->candidates->findByEmail($row['email'] ?? null)
            ?? $this->candidates->findByPhone($row['phone'] ?? null);

        $candidateCreated = false;

        if ($existing === null) {
            $candidate = DB::transaction(function () use ($row, $uploader): Candidate {
                return $this->candidates->create([
                    'candidate_number' => $this->numbers->nextCandidateNumber(),
                    'full_name' => $row['full_name'],
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'country' => $row['country'] ?? null,
                    'city' => $row['city'] ?? null,
                    'linkedin_url' => $row['linkedin_url'] ?? null,
                    'headline' => $row['headline'] ?? null,
                    'years_of_experience' => $row['years_of_experience'] ?? null,
                    'current_title' => $row['current_title'] ?? null,
                    'current_company' => $row['current_company'] ?? null,
                    'expected_salary_min' => $row['expected_salary_min'] ?? null,
                    'expected_salary_max' => $row['expected_salary_max'] ?? null,
                    'salary_currency' => $row['salary_currency'] ?? 'USD',
                    'availability' => $row['availability'] ?? null,
                    'skills' => $row['skills'] ?? null,
                    'languages' => $row['languages'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'source' => 'csv_import',
                    'created_by_user_id' => $uploader->id,
                ]);
            });
            $candidateCreated = true;
        } else {
            $candidate = $existing;
        }

        try {
            $this->applications->attach($job, $candidate, $uploader, ['source' => 'csv_import']);
            $applicationCreated = true;
        } catch (\Illuminate\Validation\ValidationException) {
            // Already-applied is the one duplicate the attach service
            // raises — treated as a soft skip per plan §9.3 step 4,
            // NOT a hard failure. Any other validation shape surfaces
            // through the Throwable catch in processChunk().
            $applicationCreated = false;
        }

        return [
            'candidate' => $candidate,
            'candidate_created' => $candidateCreated,
            'application_created' => $applicationCreated,
        ];
    }

    /**
     * Read + normalise header-keyed rows from a CSV or XLSX file.
     * Uploaded files are passed as the UploadedFile itself; the worker
     * passes a string path from the local disk. Capped at MAX_ROWS to
     * block a 50k-row surprise that would starve the queue worker.
     *
     * @return list<array<string, mixed>>
     */
    private function readRows(UploadedFile|string $file): array
    {
        $import = new class implements ToArray, WithHeadingRow
        {
            /**
             * @var list<array<string, mixed>>
             */
            public array $rows = [];

            /**
             * @param  array<int, array<string, mixed>>  $array
             */
            public function array(array $array): void
            {
                $this->rows = array_values($array);
            }
        };

        Excel::import($import, $file);

        return array_slice($import->rows, 0, self::MAX_ROWS);
    }

    /**
     * Trim strings, lowercase email, split pipe-separated skills /
     * languages into arrays. Null-safe for every column so the preview
     * can show "this field was blank" in a stable shape.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normaliseRow(array $row): array
    {
        $out = [];

        foreach (self::TEMPLATE_HEADERS as $key) {
            $raw = $row[$key] ?? null;

            // Excel hands back digit-only cells (phones, ZIP-like values) as int/float.
            if (in_array($key, self::STRING_COLUMNS, true) && (is_int($raw) || is_float($raw))) {
                $raw = (string) $raw;
            }

            if (is_string($raw)) {
                $raw = trim($raw);
                $raw = $raw === '' ? null : $raw;
            }

            $out[$key] = $raw;
        }

        if (isset($out['email']) && is_string($out['email'])) {
            $out['email'] = mb_strtolower($out['email']);
        }

        foreach (['skills', 'languages'] as $pipeSeparated) {
            if (is_string($out[$pipeSeparated] ?? null)) {
                $parts = array_values(array_filter(
                    array_map('trim', explode('|', $out[$pipeSeparated])),
                    static fn (string $v): bool => $v !== '',
                ));
                $out[$pipeSeparated] = $parts === [] ? null : $parts;
            }
        }

        foreach (['years_of_experience', 'expected_salary_min', 'expected_salary_max'] as $numeric) {
            if (($out[$numeric] ?? null) !== null && ! is_array($out[$numeric])) {
                $out[$numeric] = is_numeric($out[$numeric]) ? $out[$numeric] + 0 : $out[$numeric];
            }
        }

        return $out;
    }

    /**
     * Returns the per-row error entries (plan §9.4 shape). Empty when
     * the row is valid.
     *
     * @param  array<string, mixed>  $row
     * @return list<array{row:int,field:string,message:string}>
     */
    private function validateRow(int $rowNumber, array $row): array
    {
        $validator = Validator::make($row, [
            'full_name' => ['required', 'string', 'min:2', 'max:200'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'phone' => ['nullable', 'string', 'min:7', 'max:30'],
            'country' => ['nullable', 'string', 'min:2', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'headline' => ['nullable', 'string', 'max:200'],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'current_title' => ['nullable', 'string', 'max:150'],
            'current_company' => ['nullable', 'string', 'max:150'],
            'expected_salary_min' => ['nullable', 'numeric', 'min:0'],
            'expected_salary_max' => ['nullable', 'numeric', 'gte:expected_salary_min'],
            'salary_currency' => ['nullable', 'string', 'size:3'],
            'availability' => ['nullable', 'in:immediate,2_weeks,1_month,negotiable'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $errors = [];

        foreach ($validator->errors()->toArray() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = ['row' => $rowNumber, 'field' => (string) $field, 'message' => (string) $message];
            }
        }

        // Dedup requires AT LEAST one of email/phone — matches plan §9.2.
        // Checked outside the validator so the message is unambiguous.
        if (($row['email'] ?? null) === null && ($row['phone'] ?? null) === null) {
            $errors[] = [
                'row' => $rowNumber,
                'field' => '__row__',
                'message' => 'يجب تعبئة البريد الإلكتروني أو رقم الهاتف على الأقل.',
            ];
        }

        return $errors;
    }
}
