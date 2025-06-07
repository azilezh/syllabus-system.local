// Функция подтверждения действия с использованием Bootstrap модального окна
function ConfirmModal(message, callback) {
    // Создаем модальное окно динамически
    const modalHtml = `
        <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="confirmModalLabel">Подтверждение</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        ${message}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="button" class="btn btn-primary" id="confirmAction">Подтвердить</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    // Добавляем модальное окно в DOM
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    modal.show();

    // Обработчик подтверждения
    document.getElementById('confirmAction').addEventListener('click', () => {
        modal.hide();
        callback(true);
        document.getElementById('confirmModal').remove(); // Удаляем модальное окно
    });

    // Обработчик закрытия
    document.getElementById('confirmModal').addEventListener('hidden.bs.modal', () => {
        document.getElementById('confirmModal').remove();
    });
}

// Валидация форм
document.addEventListener('DOMContentLoaded', function () {
    // Валидация формы добавления пользователя
    const addUserForm = document.getElementById('addUserForm');
    if (addUserForm) {
        addUserForm.addEventListener('submit', function (e) {
            const login = document.getElementById('login').value.trim();
            const password = document.getElementById('password').value.trim();
            const name = document.getElementById('name').value.trim();
            const role = document.getElementById('role').value;

            if (!login || !password || !name || !role) {
                e.preventDefault();
                alert('Заполните все поля формы!');
                return;
            }
            if (login.length < 3) {
                e.preventDefault();
                alert('Логин должен быть не короче 3 символов!');
                return;
            }
            if (password.length < 6) {
                e.preventDefault();
                alert('Пароль должен быть не короче 6 символов!');
                return;
            }
        });
    }

    // Валидация формы редактирования пользователя
    const editUserForms = document.querySelectorAll('[id^="editUserForm"]');
    editUserForms.forEach(form => {
        form.addEventListener('submit', function (e) {
            const login = form.querySelector('input[name="edit_login"]').value.trim();
            const name = form.querySelector('input[name="edit_name"]').value.trim();
            const role = form.querySelector('select[name="edit_role_id"]').value;
            const status = form.querySelector('select[name="edit_status"]').value;

            if (!login || !name || !role || !status) {
                e.preventDefault();
                alert('Заполните все поля формы!');
                return;
            }
            if (login.length < 3) {
                e.preventDefault();
                alert('Логин должен быть не короче 3 символов!');
                return;
            }
        });
    });

    // Валидация формы добавления курса
    const addCourseForm = document.getElementById('addCourseForm');
    if (addCourseForm) {
        addCourseForm.addEventListener('submit', function (e) {
            const title = document.getElementById('course_title').value.trim();
            if (!title) {
                e.preventDefault();
                alert('Укажите название курса!');
                return;
            }
            if (title.length < 3) {
                e.preventDefault();
                alert('Название курса должно быть не короче 3 символов!');
                return;
            }
        });
    }

    // Валидация формы добавления силлабуса/материала
    const addSyllabusForm = document.getElementById('addSyllabusForm');
    if (addSyllabusForm) {
        addSyllabusForm.addEventListener('submit', function (e) {
            const courseId = document.getElementById('course_id_syllabus').value;
            const title = document.getElementById('syllabus_title').value.trim();
            const content = document.getElementById('content').value.trim();
            const file = document.getElementById('material_file').files[0];

            if (!courseId || !title) {
                e.preventDefault();
                alert('Выберите курс и укажите название!');
                return;
            }
            if (title.length < 3) {
                e.preventDefault();
                alert('Название должно быть не короче 3 символов!');
                return;
            }
            if (!content && !file) {
                e.preventDefault();
                alert('Укажите содержимое силлабуса или загрузите файл!');
                return;
            }
            if (file) {
                const allowedTypes = [
                    'application/pdf',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation'
                ];
                const maxSize = 10 * 1024 * 1024; // 10 МБ
                if (!allowedTypes.includes(file.type)) {
                    e.preventDefault();
                    alert('Допустимые форматы файлов: PDF, PPT, PPTX.');
                    return;
                }
                if (file.size > maxSize) {
                    e.preventDefault();
                    alert('Размер файла не должен превышать 10 МБ.');
                    return;
                }
            }
        });
    }

    // Валидация формы добавления события
    const addEventForm = document.getElementById('addEventForm');
    if (addEventForm) {
        addEventForm.addEventListener('submit', function (e) {
            const title = document.getElementById('title').value.trim();
            const courseId = document.getElementById('course_id_event').value;
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;

            if (!title || !courseId || !startTime) {
                e.preventDefault();
                alert('Заполните обязательные поля: название, курс и время начала!');
                return;
            }
            if (title.length < 3) {
                e.preventDefault();
                alert('Название события должно быть не короче 3 символов!');
                return;
            }
            if (endTime && endTime < startTime) {
                e.preventDefault();
                alert('Время окончания не может быть раньше времени начала!');
                return;
            }
        });
    }

    // Валидация формы выставления оценки
    const addGradeForm = document.getElementById('addGradeForm');
    if (addGradeForm) {
        addGradeForm.addEventListener('submit', function (e) {
            const studentId = document.getElementById('student_id_grade').value;
            const courseId = document.getElementById('course_id_grade').value;
            const grade = document.getElementById('grade').value;
            const status = document.getElementById('status_grade').value;

            if (!studentId || !courseId || !grade || !status) {
                e.preventDefault();
                alert('Заполните все поля формы!');
                return;
            }
            if (grade < 0 || grade > 100) {
                e.preventDefault();
                alert('Оценка должна быть в диапазоне от 0 до 100!');
                return;
            }
        });
    }

    // Валидация формы редактирования оценки
    const editGradeForms = document.querySelectorAll('[id^="editGradeForm"]');
    editGradeForms.forEach(form => {
        form.addEventListener('submit', function (e) {
            const grade = form.querySelector('input[name="grade"]').value;
            const status = form.querySelector('select[name="status"]').value;

            if (!grade || !status) {
                e.preventDefault();
                alert('Заполните все поля формы!');
                return;
            }
            if (grade < 0 || grade > 100) {
                e.preventDefault();
                alert('Оценка должна быть в диапазоне от 0 до 100!');
                return;
            }
        });
    });

    // Валидация формы записи на курс
    const enrollCourseForm = document.getElementById('enrollCourseForm');
    if (enrollCourseForm) {
        enrollCourseForm.addEventListener('submit', function (e) {
            const courseId = document.getElementById('course_id_enroll').value;
            if (!courseId) {
                e.preventDefault();
                alert('Выберите курс!');
                return;
            }
        });
    }
});