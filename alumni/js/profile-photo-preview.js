document.addEventListener("DOMContentLoaded", function () {
    const input = document.getElementById("profile_photo");
    const img = document.getElementById("profilePreview");

    input.addEventListener("change", function () {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
});
