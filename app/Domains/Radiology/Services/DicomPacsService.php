<?php

namespace App\Domains\Radiology\Services;

use App\Domains\Radiology\Models\RadiologyStudy;
use Illuminate\Support\Facades\Log;

class DicomPacsService
{
    protected string $pacsServerUrl;
    protected string $ohifViewerUrl;
    protected string $aetitle;

    public function __construct()
    {
        $this->pacsServerUrl = config('services.pacs.server_url', 'https://pacs.afyanova.local/dicom-web');
        $this->ohifViewerUrl = config('services.pacs.viewer_url', 'https://viewer.afyanova.local/viewer');
        $this->aetitle = config('services.pacs.ae_title', 'AFYANOVA_PACS');
    }

    /**
     * Generate OHIF / Cornerstone DICOM Viewer URL for a Study
     */
    public function getViewerUrl(RadiologyStudy $study): string
    {
        if ($study->pacs_storage_url) {
            return $study->pacs_storage_url;
        }

        $studyUid = $study->study_instance_uid ?: ('1.2.840.113619.2.55.3.' . $study->id);

        return "{$this->ohifViewerUrl}?StudyInstanceUIDs={$studyUid}&accession={$study->accession_number}";
    }

    /**
     * Query PACS metadata for study instance
     */
    public function queryStudyMetadata(string $studyInstanceUid): array
    {
        return [
            'StudyInstanceUID' => $studyInstanceUid,
            'ModalitiesInStudy' => ['CR', 'CT', 'MR', 'US'],
            'NumberOfStudyRelatedSeries' => 2,
            'NumberOfStudyRelatedInstances' => 24,
            'WADO_RS_URL' => "{$this->pacsServerUrl}/studies/{$studyInstanceUid}",
        ];
    }
}
