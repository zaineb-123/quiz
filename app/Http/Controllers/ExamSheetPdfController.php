<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExamSheetPdfController extends Controller
{
    /**
     * Teacher-only printable version of an exam.
     * Default: blank sheet for students. With ?key=1: answer key with the correct answers.
     */
    public function __invoke(Request $request, Exam $exam): Response
    {
        abort_unless($exam->user_id === $request->user()->id, 403);

        $withAnswers = $request->boolean('key');

        $pdf = Pdf::loadView('pdf.exam-sheet', [
            'exam' => $exam->load('questions'),
            'withAnswers' => $withAnswers,
        ]);

        $suffix = $withAnswers ? 'answer-key' : 'sheet';

        return $pdf->download(str($exam->title)->slug().'-'.$suffix.'.pdf');
    }
}
