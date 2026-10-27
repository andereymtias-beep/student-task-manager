
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.delete-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const confirmed = window.confirm(
                'Are you sure you want to delete this task? This action cannot be undone.'
            );

            if (!confirmed) {
                event.preventDefault();
            }
        });
    });
});
