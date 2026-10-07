<?php
/* Course card template.
   Keep your existing working href/action URLs if they differ. */

$courseId = (int)($course['id'] ?? 0);
$courseTitle = trim((string)($course['title'] ?? 'Без назви'));
$courseDescription = trim((string)($course['description'] ?? ''));
$studentsCount = (int)($course['students_count'] ?? 0);
$source = trim((string)($course['source'] ?? ''));
?>

<div class="course-card">

    <div class="course-card__top">
        <div class="course-card__icon">📚</div>

        <div>
            <h3 class="course-card__title">
                <?= htmlspecialchars($courseTitle, ENT_QUOTES, 'UTF-8') ?>
            </h3>
        </div>
    </div>

    <?php if ($courseDescription !== ''): ?>
        <p class="course-card__description">
            <?= nl2br(htmlspecialchars($courseDescription, ENT_QUOTES, 'UTF-8')) ?>
        </p>
    <?php else: ?>
        <p class="course-card__description is-empty">
            Опис курсу не додано
        </p>
    <?php endif; ?>

    <div class="course-card__meta">
        <div class="course-card__meta-item">
            👨‍🎓
            <span>
                <?= $studentsCount ?>
                <?= $studentsCount === 1 ? 'учень' : 'учнів' ?>
            </span>
        </div>
    </div>

    <?php if ($source !== ''): ?>
        <div class="course-card__source">
            <strong>Джерело:</strong>
            <?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="course-card__actions">

        <a
            class="course-card__btn course-card__btn--primary"
            href="index.php?url=course/<?= $courseId ?>"
        >
            ▶ Відкрити
        </a>

        <a
            class="course-card__btn course-card__btn--secondary"
            href="index.php?url=course/edit/<?= $courseId ?>"
        >
            ✏️ Редагувати
        </a>

        <form
            method="post"
            action="index.php?url=course/delete/<?= $courseId ?>"
            onsubmit="return confirm('Видалити цей курс?\\n\\nУсі пов’язані матеріали можуть бути видалені.');"
        >
            <button
                type="submit"
                class="course-card__btn course-card__btn--danger"
            >
                🗑 Видалити
            </button>
        </form>

    </div>

</div>
