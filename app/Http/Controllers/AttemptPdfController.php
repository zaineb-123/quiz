<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttemptPdfController extends Controller
{
    /**
     * Direct PDF download of a student's own completed attempt:
     * questions, the student's answers, score and (when the teacher allows it) the correct answers.
     */
    public function __invoke(Request $request, Attempt $attempt): Response
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
        abort_unless($attempt->isCompleted(), 404);

        $attempt->load(['exam.questions', 'student']);

        $pdf = Pdf::loadView('pdf.student-result', [
            'attempt' => $attempt,
            'correctCount' => $attempt->correctCount(),
            'totalCount' => $attempt->exam->questions->count(),
            'reveal' => $attempt->answersRevealed(),
        ]);

        return $pdf->download('result-'.str($attempt->exam->title)->slug().'.pdf');
    }
}
