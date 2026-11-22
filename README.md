
# QuizForge

**AI-Powered Quiz Generation and Learning Management Platform**

QuizForge is an intelligent learning platform designed to simplify quiz creation, assessment, and student progress monitoring using Artificial Intelligence.

The platform allows teachers to create and manage courses, publish learning materials, generate AI-powered quizzes, assign assessments to students, and monitor their academic performance.

Students can access their assigned quizzes, answer questions, receive automatic corrections, and track their learning progress.

---

## Features

### AI-Powered Quiz Generation
- Automatically generate quizzes using Artificial Intelligence.
- Generate questions based on educational content.
- Support different question types.
- Use AI agents to assist with quiz generation and evaluation.

### Quiz Management
- Create, edit, and manage quizzes.
- Assign quizzes to students and classes.
- Manage questions and answers.
- Automatically correct student submissions.
- Calculate and display quiz scores.

### Teacher Management
- Manage courses and classrooms.
- Upload and publish educational materials.
- Create and assign quizzes.
- Monitor student performance and progress.

### Student Management
- Access assigned courses and quizzes.
- Complete online assessments.
- View results and scores.
- Track learning progress.

### PDF Support
- Export quizzes and assessment results.
- Download generated quiz documents.
- Support for educational document workflows.

### User Roles
- Teacher
- Student

---

## Technologies Used

| Category | Technologies |
|---|---|
| Backend | Laravel 12, PHP |
| Frontend | Livewire, Blade, Tailwind CSS |
| Database | PostgreSQL |
| Vector Search | pgvector |
| Artificial Intelligence | Laravel AI SDK, Ollama |
| AI Model | Qwen 2.5 |
| Development Tools | Git, GitHub, Docker |

---

## Artificial Intelligence

QuizForge integrates Artificial Intelligence to support educational assessment workflows.

The AI architecture includes specialized agents:

- **QuestionGeneratorAgent:** Generates quiz questions from educational content.
- **AutoGraderAgent:** Evaluates student answers automatically.
- **HintAgent:** Provides hints to support student learning.

Additional AI components include:

- Question similarity detection.
- Embeddings and vector search.
- SimilaritySearch Tool.
- Cost and model configuration management.

### Supported AI Providers

The architecture supports different AI providers, depending on the application configuration:

- Ollama
- OpenAI
- Anthropic
- Google Gemini

**Local AI configuration:** The project uses Ollama with the Qwen 2.5 model.

---

## Project Architecture

QuizForge follows a Laravel-based application architecture.

Main components include:

- Authentication and authorization.
- Teacher and student management.
- Course and classroom management.
- Educational content management.
- AI-powered quiz generation.
- Automatic assessment and correction.
- Student progress monitoring.
- PDF export functionality.

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/zaineb-123/quiz.git
cd quiz
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install frontend dependencies

```bash
npm install
```

### 4. Configure environment variables

```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

Configure your database credentials and AI settings in the `.env` file.

### 5. Run database migrations

```bash
php artisan migrate
```

### 6. Build frontend assets

```bash
npm run build
```

### 7. Start the Laravel development server

```bash
php artisan serve
```

For frontend development:

```bash
npm run dev
```

---

## AI Configuration with Ollama

Install and start Ollama, then download the Qwen model:

```bash
ollama pull qwen2.5:1.5b
```

Start the Ollama service:

```bash
ollama serve
```

Configure the AI provider and model in your `.env` file according to the project's AI SDK configuration.

---

## Project Goals

QuizForge aims to:

- Reduce the time required to create educational assessments.
- Improve the quiz creation process through AI.
- Simplify student evaluation.
- Provide teachers with useful student progress insights.
- Support personalized and interactive learning experiences.

---

## Future Improvements

- Advanced PDF-based quiz generation.
- More AI-powered learning features.
- Enhanced student analytics.
- Additional question formats.
- Improved learning recommendations.

---

## Acknowledgements

QuizForge is based on and extends the open-source project [Laravel AI Quiz Engine](https://github.com/jaygaha/laravel-ai-quiz-engine).

The project has been adapted and further developed with additional customizations, features, and interface improvements.

Original licensing and copyright notices are preserved.

---

## Author

**Zaineb Mhenni**

Web and Multimedia Development Student  
ISET Mahdia

GitHub: [zaineb-123](https://github.com/zaineb-123)

---

**Repository:** [QuizForge](https://github.com/zaineb-123/quiz)
