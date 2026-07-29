<?php

namespace Modules\MetaAdsImageGenerator\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Modules\MetaAdsImageGenerator\Models\AdExport;
use Modules\MetaAdsImageGenerator\Models\AdGeneration;
use ZipArchive;

class ExportController extends Controller
{
    public function download(AdExport $export)
    {
        $generation = $export->generation;
        abort_if($generation->user_id !== Auth::id(), 403);

        if (!$export->final_image_path || !Storage::disk('public')->exists($export->final_image_path)) {
            return back()->with('error', 'File not found.');
        }

        $export->update(['downloaded_at' => now()]);

        return Storage::disk('public')->download(
            $export->final_image_path,
            sprintf('%s_%s.png', $generation->id, $export->placement)
        );
    }

    public function downloadZip(AdGeneration $generation)
    {
        abort_if($generation->user_id !== Auth::id(), 403);

        $exports = $generation->exports;
        if ($exports->isEmpty()) {
            return back()->with('error', 'No exports available.');
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'exports_') . '.zip';
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            return back()->with('error', 'Failed to create ZIP archive.');
        }

        foreach ($exports as $export) {
            if ($export->final_image_path && Storage::disk('public')->exists($export->final_image_path)) {
                $zip->addFile(
                    Storage::disk('public')->path($export->final_image_path),
                    sprintf('%s_%dx%d.png', $export->placement, $export->width, $export->height)
                );
                $export->update(['downloaded_at' => now()]);
            }
        }

        $zip->close();

        return response()->download($zipPath, "generation_{$generation->id}.zip")->deleteFileAfterSend(true);
    }
}
