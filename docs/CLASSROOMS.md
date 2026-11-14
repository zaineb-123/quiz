# Classes, matières et assignation des quiz

## Le principe

Avant, tout examen *publié* était visible par **tous** les étudiants. Maintenant, un étudiant ne voit un
examen que si les 3 conditions sont réunies :

1. l'examen est **publié** (`exams.published_at`) ;
2. l'examen est **assigné** à une classe (`exam_assignments`) ;
3. l'étudiant a **rejoint** cette classe avec son code (`classroom_student`) — et la fenêtre
   `opens_at` / `closes_at` de l'assignation est ouverte.

## Tables ajoutées

| Table | Rôle |
|---|---|
| `classrooms` | Classe d'un prof : nom, année, `join_code` unique (8 caractères), `join_enabled` |
| `classroom_student` | Étudiants inscrits (unique par classe + étudiant) |
| `subjects` | Matières d'une classe (facultatives) |
| `exam_assignments` | Publie un examen dans une classe : `subject_id`, `opens_at`, `closes_at`, `reveal_answers_at` |

`exams` et `attempts` ne changent pas : un même examen peut être réutilisé dans plusieurs classes, avec des dates différentes.

## Parcours

**Prof** : `Mes classes` → créer une classe → copier le code → ajouter des matières → *Assigner un examen*
(matière, ouverture, date limite, date de révélation des corrections) → suivre le tableau étudiants × examens.
N'oubliez pas de **publier** l'examen dans `Mes examens` (un brouillon assigné reste invisible).

**Étudiant** : `Mes classes` → saisir le code → voir les matières et les examens de la classe.

## Sécurité

- `ClassroomPolicy` : `manage` (prof propriétaire) et `view` (propriétaire ou étudiant inscrit).
- Contrôle **côté serveur** sur : passage d'examen, classement, pages de classe, PDF.
- Un étudiant ne peut pas ouvrir un examen par URL directe s'il n'est pas dans la classe.
- Saisie du code limitée à 8 essais ratés par minute ; même message d'erreur pour « code faux » et « inscriptions fermées ».
- Régénérer le code invalide l'ancien (les étudiants déjà inscrits restent).
- Une classe supprimée (soft delete) retire l'accès à ses examens.
- Les recommandations (« Recommended Practice ») ne proposent que des examens accessibles à l'étudiant.

## PDF

| PDF | Qui | Route |
|---|---|---|
| Résultat d'une tentative (questions, réponses, note, corrigé) | l'étudiant propriétaire, après soumission | `student.attempts.pdf` |
| Examen vierge / corrigé (`?key=1`) | le prof propriétaire | `teacher.exams.pdf` |
| Rapport de classe (étudiants × examens, moyennes, taux de réussite) | le prof propriétaire | `teacher.classrooms.report` |

`reveal_answers_at` : si une date future est définie, l'étudiant voit sa note et ses réussites/échecs, mais **pas** les bonnes
réponses ni les explications IA (page de résultats **et** PDF), pour éviter qu'un étudiant qui a fini tôt ne partage le corrigé.

## Mise à jour d'une base existante

Les examens déjà publiés n'ont pas d'assignation : ils deviennent invisibles pour les étudiants tant que le prof ne les
assigne pas à une classe. En développement : `php artisan migrate:fresh --seed` crée deux classes de démo
(codes `DEMO2026` et `WEBDB2026`).

## Tests

`php artisan test --compact tests/Feature/ClassroomTest.php` puis toute la suite. Les anciens tests qui font entrer un
étudiant dans un examen utilisent le helper `giveStudentAccess($student, $exam)` (défini dans `tests/Pest.php`).
