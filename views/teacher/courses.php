<?php
$pageTitle = 'Мої курси';
?>

<div class="teacher-dashboard">

    <section class="teacher-dashboard__hero">
        <div class="teacher-dashboard__hero-content">
            <div class="teacher-dashboard__eyebrow">
                <span class="teacher-dashboard__eyebrow-dot"></span>
                QuizSpace • Кабінет викладача
            </div>

            <h1>Мої курси</h1>

            <p>
                Створюйте навчальні матеріали, тести, картки та вправи
                в одному місці.
            </p>
        </div>

        <?php if($me['role']==='teacher'): ?>
            <a class="teacher-dashboard__create" href="<?=url('course-create')?>">
                <span>＋</span>
                <span>Створити курс</span>
            </a>
        <?php endif; ?>
    </section>


    <section class="teacher-dashboard__toolbar">

        <div class="teacher-dashboard__search">
            <span>⌕</span>
            <input
                id="teacherCourseSearch"
                type="search"
                placeholder="Пошук курсу..."
                autocomplete="off"
            >
        </div>

        <div class="teacher-dashboard__counter">
            <strong id="teacherCourseCount"><?=count($courses)?></strong>
            <span>курсів</span>
        </div>

    </section>


    <section class="teacher-courses-grid" id="teacherCoursesGrid">

        <?php foreach($courses as $c): ?>

            <?php
                $title = trim((string)($c['title'] ?? 'Без назви'));
                $description = trim((string)($c['description'] ?? ''));
                $students = (int)($c['students'] ?? 0);
            ?>

            <article
                class="teacher-course-card"
                data-course-title="<?=e(mb_strtolower($title))?>"
            >

                <div class="teacher-course-card__accent"></div>

                <div class="teacher-course-card__top">

                    <div class="teacher-course-card__icon">
                        📚
                    </div>

                    <button
                        type="button"
                        class="teacher-course-card__more"
                        aria-label="Дії з курсом"
                        onclick="this.closest('.teacher-course-card').classList.toggle('is-menu-open')"
                    >
                        ⋮
                    </button>

                    <div class="teacher-course-card__menu">

                        <a href="<?=url('course/'.$c['id'])?>">
                            ▶ Відкрити
                        </a>

                        <?php if($me['role']==='teacher'): ?>
                            <a href="<?=url('course-edit/'.$c['id'])?>">
                                ✏️ Редагувати
                            </a>
                        <?php endif; ?>

                    </div>

                </div>


                <div class="teacher-course-card__body">

                    <h2>
                        <?=e($title)?>
                    </h2>

                    <?php if($description !== ''): ?>
                        <p>
                            <?=e($description)?>
                        </p>
                    <?php else: ?>
                        <p class="is-empty">
                            Опис курсу ще не додано
                        </p>
                    <?php endif; ?>

                </div>


                <div class="teacher-course-card__stats">
                    <div><span class="teacher-course-card__stat-icon">👨‍🎓</span><span><strong><?=$students?></strong> <?=($students === 1 ? 'учень' : 'учнів')?></span></div>
                    <div><span class="teacher-course-card__stat-icon">📝</span><span><strong><?=((int)($c['normal_quiz_count']??0))?></strong> тестів</span></div>
                    <div><span class="teacher-course-card__stat-icon">❓</span><span><strong><?=((int)($c['qa_quiz_count']??0))?></strong> квизов П→О</span></div>
                    <div><span class="teacher-course-card__stat-icon">🎮</span><span><strong><?=((int)($c['interactive_quiz_count']??0))?></strong> інтерактивних</span></div>

                    <?php if($me['role']==='admin' && !empty($c['teacher_name'])): ?>
                        <div>
                            <span class="teacher-course-card__stat-icon">👨‍🏫</span>
                            <span><?=e($c['teacher_name'])?></span>
                        </div>
                    <?php endif; ?>

                </div>


                <div class="teacher-course-card__footer">

                    <a
                        class="teacher-course-card__open"
                        href="<?=url('course/'.$c['id'])?>"
                    >
                        Відкрити курс
                        <span>→</span>
                    </a>

                    <?php if($me['role']==='teacher'): ?>

                        <a
                            class="teacher-course-card__edit"
                            href="<?=url('course-edit/'.$c['id'])?>"
                            title="Редагувати курс"
                        >
                            ✏️
                        </a>

                        <form
                            method="post"
                            action="<?=url('delete/'.$c['id'].'/course')?>"
                            class="teacher-course-card__delete-form"
                            data-course-delete
                            data-course-name="<?=e($title)?>"
                        >
                            <input
                                type="hidden"
                                name="csrf"
                                value="<?=csrf()?>"
                            >

                            <button
                                type="submit"
                                class="teacher-course-card__delete"
                                title="Видалити курс"
                            >
                                🗑
                            </button>
                        </form>

                    <?php endif; ?>

                </div>

            </article>

        <?php endforeach; ?>

    </section>


    <div class="teacher-courses-empty-search" id="teacherCourseNoResults">
        <div>🔎</div>
        <h3>Курс не знайдено</h3>
        <p>Спробуйте змінити пошуковий запит.</p>
    </div>


    <?php if(!$courses): ?>

        <div class="teacher-courses-empty">
            <div class="teacher-courses-empty__icon">📚</div>

            <h3>Поки що курсів немає</h3>

            <p>
                Створіть перший курс, щоб додавати тести,
                питання, картки та вправи.
            </p>

            <?php if($me['role']==='teacher'): ?>
                <a class="teacher-dashboard__create" href="<?=url('course-create')?>">
                    ＋ Створити курс
                </a>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>


<div class="teacher-delete-modal" id="teacherDeleteModal" aria-hidden="true">

    <div class="teacher-delete-modal__backdrop" data-delete-close></div>

    <div class="teacher-delete-modal__box" role="dialog" aria-modal="true">

        <div class="teacher-delete-modal__icon">
            🗑
        </div>

        <h3>Видалити курс?</h3>

        <p>
            Ви збираєтесь видалити
            <strong id="teacherDeleteCourseName"></strong>.
            Пов'язані навчальні матеріали також можуть бути видалені.
        </p>

        <div class="teacher-delete-modal__actions">
            <button
                type="button"
                class="teacher-delete-modal__cancel"
                data-delete-close
            >
                Скасувати
            </button>

            <button
                type="button"
                class="teacher-delete-modal__confirm"
                id="teacherDeleteConfirm"
            >
                Видалити
            </button>
        </div>

    </div>
</div>


<script>
(function () {
    const search = document.getElementById('teacherCourseSearch');
    const grid = document.getElementById('teacherCoursesGrid');
    const count = document.getElementById('teacherCourseCount');
    const noResults = document.getElementById('teacherCourseNoResults');

    if (search && grid) {
        const cards = Array.from(
            grid.querySelectorAll('.teacher-course-card')
        );

        function filterCourses() {
            const query = search.value.trim().toLowerCase();
            let visible = 0;

            cards.forEach(card => {
                const title = card.dataset.courseTitle || '';
                const show = !query || title.includes(query);

                card.hidden = !show;

                if (show) visible++;
            });

            if (count) count.textContent = visible;
            if (noResults) noResults.classList.toggle('is-visible', visible === 0);
        }

        search.addEventListener('input', filterCourses);
    }


    const modal = document.getElementById('teacherDeleteModal');
    const modalName = document.getElementById('teacherDeleteCourseName');
    const confirmButton = document.getElementById('teacherDeleteConfirm');

    let pendingForm = null;

    function closeDeleteModal() {
        if (!modal) return;

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        pendingForm = null;
    }

    document.querySelectorAll('[data-course-delete]').forEach(form => {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            pendingForm = form;

            if (modalName) {
                modalName.textContent =
                    form.dataset.courseName || 'цей курс';
            }

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        });
    });

    document.querySelectorAll('[data-delete-close]').forEach(button => {
        button.addEventListener('click', closeDeleteModal);
    });

    if (confirmButton) {
        confirmButton.addEventListener('click', function () {
            if (pendingForm) {
                HTMLFormElement.prototype.submit.call(pendingForm);
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeDeleteModal();
        }
    });

    document.addEventListener('click', function (event) {
        document.querySelectorAll('.teacher-course-card.is-menu-open').forEach(card => {
            if (!card.contains(event.target)) {
                card.classList.remove('is-menu-open');
            }
        });
    });
})();
</script>
