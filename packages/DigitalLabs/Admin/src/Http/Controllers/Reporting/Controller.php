<?php

namespace DigitalLabs\Admin\Http\Controllers\Reporting;

use Maatwebsite\Excel\Facades\Excel;
use DigitalLabs\Admin\Exports\ReportingExport;
use DigitalLabs\Admin\Helpers\Reporting as ReportingHelper;
use DigitalLabs\Admin\Http\Controllers\Controller as BaseController;

class Controller extends BaseController
{
    /**
     * Request param functions.
     *
     * @var array
     */
    protected $typeFunctions = [];

    /**
     * Create a controller instance.
     *
     * @return void
     */
    public function __construct(protected ReportingHelper $reportingHelper) {}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats()
    {
        $stats = $this->reportingHelper->{$this->resolveTypeFunction()}();

        return response()->json([
            'statistics' => $stats,
            'date_range' => $this->reportingHelper->getDateRange(),
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function viewStats()
    {
        $stats = $this->reportingHelper->{$this->resolveTypeFunction()}('table');

        return response()->json([
            'statistics' => $stats,
            'date_range' => $this->reportingHelper->getDateRange(),
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export()
    {
        $stats = $this->reportingHelper->{$this->resolveTypeFunction()}('table');

        return Excel::download(new ReportingExport($stats), request()->query('type').'.'.request()->query('format'));
    }

    /**
     * Validate if the requested type is valid.
     *
     * @return void
     */
    protected function validateRequestedType()
    {
        return ! array_key_exists(request()->query('type'), $this->typeFunctions);
    }

    /**
     * Resolve the requested type into a valid function name.
     *
     * @return string
     */
    protected function resolveTypeFunction()
    {
        if ($this->validateRequestedType()) {
            abort(404);
        }

        return $this->typeFunctions[request()->query('type')];
    }
}
