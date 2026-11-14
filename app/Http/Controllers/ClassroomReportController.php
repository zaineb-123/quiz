<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Services\ClassroomProgressService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Gate;

class ClassroomReportController extends Controller
{
    /** Teacher-only PDF report: students × exams, averages and pass rates for one classroom. */
    public function __invoke(Request $request, Classroom $classroom, ClassroomProgressService $progress): Response
    {
        Gate::authorize('manage', $classroom);

        $pdf = Pdf::loadView('pdf.classroom-report', [
            'classroom' => $classroom->load('teacher'),
            'progress' => $progress->matrix($classroom),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('class-report-'.str($classroom->name)->slug().'.pdf');
    }
}
