<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LargeFileController extends Controller
{
    // chunks 暫存：storage/app
    private string $tmpDisk = 'local';
    private string $tmpRoot = 'large_uploads';

    // 最終檔案：storage/app/public
    private string $finalDisk = 'public';
    private string $finalRoot = 'uploads';

    private function metaPath(string $uploadId): string
    {
        return "{$this->tmpRoot}/{$uploadId}/meta.json";
    }

    private function chunkPath(string $uploadId, int $chunkNumber): string
    {
        return "{$this->tmpRoot}/{$uploadId}/chunks/chunk_{$chunkNumber}";
    }

    private function lockPath(string $uploadId): string
    {
        return "{$this->tmpRoot}/{$uploadId}/merge.lock";
    }

    /**
     * POST /api/large-file/init
     * body: { fileName, fileSize, totalChunks, fileHash? }
     */
    public function init(Request $request)
    {
        $request->validate([
            'fileName' => 'required|string',
            'fileSize' => 'required|integer|min:1',
            'totalChunks' => 'required|integer|min:1',
            'fileHash' => 'nullable|string', // 前端 MD5
        ]);

        // uploadId：優先使用 fileHash（續傳最穩），沒有就生成一個
        $uploadId = $request->input('fileHash')
            ? (string)$request->input('fileHash')
            : hash('sha256', $request->input('fileName') . '|' . $request->input('fileSize') . '|' . now()->timestamp . '|' . Str::random(8));

        $baseDir = "{$this->tmpRoot}/{$uploadId}";
        $metaPath = $this->metaPath($uploadId);

        // 第一次才建立資料夾與 meta
        if (!Storage::disk($this->tmpDisk)->exists($metaPath)) {
            Storage::disk($this->tmpDisk)->makeDirectory("{$baseDir}/chunks");

            $meta = [
                'uploadId' => $uploadId,
                'fileName' => $request->input('fileName'),
                'fileSize' => (int)$request->input('fileSize'),
                'totalChunks' => (int)$request->input('totalChunks'),
                'fileHash' => $request->input('fileHash'),
                'createdAt' => now()->toIso8601String(),
                'updatedAt' => now()->toIso8601String(),
                'merged' => false,
                'finalPath' => null,
                'downloadToken' => null,
            ];

            Storage::disk($this->tmpDisk)->put($metaPath, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        return response()->json([
            'ok' => true,
            'uploadId' => $uploadId,
        ]);
    }

    /**
     * GET /api/large-file/status?uploadId=...&totalChunks=...
     * 回傳哪些 chunk 已存在（續傳核心）
     */
    public function status(Request $request)
    {
        $request->validate([
            'uploadId' => 'required|string',
            'totalChunks' => 'required|integer|min:1',
        ]);

        $uploadId = $request->input('uploadId');
        $totalChunks = (int)$request->input('totalChunks');

        $uploaded = [];
        for ($i = 1; $i <= $totalChunks; $i++) {
            if (Storage::disk($this->tmpDisk)->exists($this->chunkPath($uploadId, $i))) {
                $uploaded[] = $i;
            }
        }

        return response()->json([
            'ok' => true,
            'uploadedChunks' => $uploaded,
        ]);
    }

    /**
     * POST /api/large-file/chunk (multipart/form-data)
     * fields: uploadId, chunkNumber, totalChunks, fileName, chunk(file)
     */
    public function chunk(Request $request)
    {
        $request->validate([
            'uploadId' => 'required|string',
            'chunkNumber' => 'required|integer|min:1',
            'totalChunks' => 'required|integer|min:1',
            'fileName' => 'required|string',
            // 'chunk' => 'required|file',
        ]);

        if (!$request->hasFile('chunk')) {
            return response()->json([
                'ok' => false,
                'message' => 'chunk missing',
                'content_length' => $request->server('CONTENT_LENGTH'),
                'post_max_size' => ini_get('post_max_size'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
            ], 422);
        }

        $file = $request->file('chunk');

        if (!$file->isValid()) {
            return response()->json([
                'ok' => false,
                'message' => 'chunk invalid',
                'php_error_code' => $file->getError(),
                'content_length' => $request->server('CONTENT_LENGTH'),
                'post_max_size' => ini_get('post_max_size'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
            ], 422);
        }

        
        $uploadId = $request->input('uploadId');
        $chunkNumber = (int)$request->input('chunkNumber');

        $chunkPath = $this->chunkPath($uploadId, $chunkNumber);

        // 已存在就跳過：支援重送與續傳
        if (Storage::disk($this->tmpDisk)->exists($chunkPath)) {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        Storage::disk($this->tmpDisk)->put($chunkPath, fopen($request->file('chunk')->getRealPath(), 'rb'));

        // 更新 meta.updatedAt（方便日後清理）
        $metaPath = $this->metaPath($uploadId);
        if (Storage::disk($this->tmpDisk)->exists($metaPath)) {
            $meta = json_decode(Storage::disk($this->tmpDisk)->get($metaPath), true) ?: [];
            $meta['updatedAt'] = now()->toIso8601String();
            Storage::disk($this->tmpDisk)->put($metaPath, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/large-file/merge
     * body: { uploadId, totalChunks, fileName, fileSize? }
     */
    public function merge(Request $request)
    {
        $request->validate([
            'uploadId' => 'required|string',
            'totalChunks' => 'required|integer|min:1',
            'fileName' => 'required|string',
            'fileSize' => 'nullable|integer|min:1',
        ]);

        $uploadId = $request->input('uploadId');
        $totalChunks = (int)$request->input('totalChunks');
        $fileName = $request->input('fileName');
        $expectedSize = $request->input('fileSize') ? (int)$request->input('fileSize') : null;

        // 合併鎖：避免同時 merge
        $lock = $this->lockPath($uploadId);
        if (Storage::disk($this->tmpDisk)->exists($lock)) {
            return response()->json(['ok' => false, 'message' => 'Merging in progress'], 409);
        }
        Storage::disk($this->tmpDisk)->put($lock, (string)now());

        try {
            // 1) 檢查 chunk 齊全
            for ($i = 1; $i <= $totalChunks; $i++) {
                if (!Storage::disk($this->tmpDisk)->exists($this->chunkPath($uploadId, $i))) {
                    return response()->json(['ok' => false, 'message' => "Missing chunk {$i}"], 409);
                }
            }

            // 2) 合併到 local 的暫存檔（串流）
            $safeName = Str::random(10) . '_' . basename($fileName);
            $tmpMergedPath = "{$this->tmpRoot}/{$uploadId}/merged.tmp";
            $tmpMergedFull = Storage::disk($this->tmpDisk)->path($tmpMergedPath);

            if (!is_dir(dirname($tmpMergedFull))) {
                mkdir(dirname($tmpMergedFull), 0775, true);
            }

            $out = fopen($tmpMergedFull, 'wb');
            try {
                for ($i = 1; $i <= $totalChunks; $i++) {
                    $chunkFull = Storage::disk($this->tmpDisk)->path($this->chunkPath($uploadId, $i));
                    $in = fopen($chunkFull, 'rb');
                    stream_copy_to_stream($in, $out);
                    fclose($in);
                }
            } finally {
                fclose($out);
            }

            // 3) 合併後校驗大小（建議）
            if ($expectedSize !== null) {
                $actualSize = filesize($tmpMergedFull);
                if ($actualSize !== $expectedSize) {
                    return response()->json([
                        'ok' => false,
                        'message' => "Merged size mismatch. expected={$expectedSize}, actual={$actualSize}"
                    ], 409);
                }
            }

            // 4) 放到 public disk（可下載）
            $finalPath = "{$this->finalRoot}/{$safeName}";
            Storage::disk($this->finalDisk)->put($finalPath, fopen($tmpMergedFull, 'rb'));

            // 5) 產生受控下載 token（可換成 DB / signed url）
            $downloadToken = hash('sha256', $uploadId . '|' . $finalPath . '|' . config('app.key'));

            // 6) 更新 meta
            $meta = [
                'uploadId' => $uploadId,
                'merged' => true,
                'finalPath' => $finalPath,
                'downloadToken' => $downloadToken,
                'updatedAt' => now()->toIso8601String(),
            ];
            Storage::disk($this->tmpDisk)->put($this->metaPath($uploadId), json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            // 7) 清理 chunks + merged.tmp（保留 meta 方便查 token）
            Storage::disk($this->tmpDisk)->deleteDirectory("{$this->tmpRoot}/{$uploadId}/chunks");
            Storage::disk($this->tmpDisk)->delete($tmpMergedPath);

            // 8) 回傳 URL
            $publicUrl = Storage::disk($this->finalDisk)->url($finalPath); // /storage/uploads/...
            $downloadUrl = url("/api/files/{$downloadToken}");

            return response()->json([
                'ok' => true,
                'finalPath' => $finalPath,
                'publicUrl' => $publicUrl,
                'downloadUrl' => $downloadUrl,
                'fileName' => $safeName,
            ]);
        } finally {
            Storage::disk($this->tmpDisk)->delete($lock);
        }
    }

    /**
     * GET /api/files/{token}
     * 受控下載（串流，不吃 RAM）
     * 注意：示範用 meta.json 掃描；大量記錄建議改 DB。
     */
    public function download(string $token)
    {
        set_time_limit(0);
        ignore_user_abort(true);

        // $root = storage_path('app/' . $this->tmpRoot);
        $root = Storage::disk($this->tmpDisk)->path($this->tmpRoot);

        if (!is_dir($root)) abort(404);

        $finalPath = null;

        foreach (glob($root . '/*/meta.json') as $metaFile) {
            $meta = json_decode(file_get_contents($metaFile), true);
            if (($meta['downloadToken'] ?? null) === $token) {
                $finalPath = $meta['finalPath'] ?? null;
                break;
            }
        }

        if (!$finalPath) abort(404);

        $finalPath = str_replace('\\', '/', $finalPath);

        $disk = Storage::disk($this->finalDisk);
        if (!$disk->exists($finalPath)) abort(404);

        $full = $disk->path($finalPath);
        $name = basename($finalPath);

        return new StreamedResponse(function () use ($full) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            $out = fopen('php://output', 'wb');
            $in = fopen($full, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
        }, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . addslashes($name) . '"',
            // 'Content-Length' => filesize($full),
        ]);
    }
}