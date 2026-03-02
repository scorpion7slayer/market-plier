document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("form").forEach(function (form) {
        form.setAttribute("novalidate", "");

        form.querySelectorAll("input, textarea, select").forEach(
            function (field) {
                field.addEventListener("input", function () {
                    clearFieldError(field);
                });
                field.addEventListener("change", function () {
                    clearFieldError(field);
                });
            },
        );

        form.addEventListener("submit", function (e) {
            if (!validateForm(form)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });
});

function validateForm(form) {
    var isValid = true;
    var fields = form.querySelectorAll(
        "input[required], textarea[required], select[required]",
    );

    fields.forEach(function (field) {
        clearFieldError(field);
    });

    fields.forEach(function (field) {
        var msg = getErrorMessage(field, form);
        if (msg) {
            showFieldError(field, msg);
            isValid = false;
        }
    });

    return isValid;
}

function getErrorMessage(field, form) {
    if (field.validity.valueMissing) {
        return "Ce champ est obligatoire";
    }
    if (field.validity.typeMismatch && field.type === "email") {
        return "Veuillez saisir une adresse email valide";
    }
    if (field.validity.tooShort) {
        var n = field.minLength;
        return "Minimum " + n + " caractère" + (n > 1 ? "s" : "") + " requis";
    }
    if (field.validity.patternMismatch) {
        return field.dataset.patternMessage || "Format invalide";
    }
    if (field.name === "confirm_password" && field.value !== "") {
        var ref =
            form.querySelector('[name="new_password"]') ||
            form.querySelector('[name="password"]');
        if (ref && field.value !== ref.value) {
            return "Les mots de passe ne correspondent pas";
        }
    }
    return null;
}

function showFieldError(field, message) {
    var span = document.createElement("span");
    span.className = "field-error";
    span.textContent = message;

    // Trouver le bon conteneur pour afficher l'erreur après
    var wrapper =
        field.closest(".custom-select-wrapper") ||
        field.closest(".password-wrapper");
    if (wrapper) {
        wrapper.insertAdjacentElement("afterend", span);
        wrapper.classList.add("field-error-active");
    } else {
        field.insertAdjacentElement("afterend", span);
        field.classList.add("field-error-active");
    }
}

function clearFieldError(field) {
    var wrapper =
        field.closest(".custom-select-wrapper") ||
        field.closest(".password-wrapper");
    var target = wrapper || field;
    var next = target.nextElementSibling;
    if (next && next.classList.contains("field-error")) {
        next.remove();
    }
    target.classList.remove("field-error-active");
    field.classList.remove("field-error-active");
}

/* Affichage/masquage du mot de passe */
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".password-toggle").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var wrapper = btn.closest(".password-wrapper");
            var input = wrapper.querySelector("input");
            var isVisible = input.type === "text";
            input.type = isVisible ? "password" : "text";
            btn.classList.toggle("visible", !isVisible);
        });
    });
});
