import './bootstrap';
import '../css/app.css';

// Funciones globales
window.openModal = function(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
}

window.closeModal = function(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

// Funciones globales para manejar formularios
window.handleFormSubmit = async function(formElement, successCallback = null) {
    try {
        const formData = new FormData(formElement);
        const response = await axios.post(formElement.action, formData);

        if (response.data.success) {
            if (successCallback) {
                successCallback(response.data);
            } else {
                window.location.reload();
            }
        }
    } catch (error) {
        console.error('Error submitting form:', error);
        if (error.response && error.response.data.errors) {
            // Mostrar errores de validación
            const errors = error.response.data.errors;
            Object.keys(errors).forEach(key => {
                const input = formElement.querySelector(`[name="${key}"]`);
                if (input) {
                    input.classList.add('border-red-500');
                    const errorMessage = document.createElement('p');
                    errorMessage.className = 'text-red-500 text-sm mt-1';
                    errorMessage.textContent = errors[key][0];
                    input.parentNode.appendChild(errorMessage);
                }
            });
        }
    }
};

// Función para limpiar errores de validación
window.clearValidationErrors = function(formElement) {
    formElement.querySelectorAll('.border-red-500').forEach(input => {
        input.classList.remove('border-red-500');
    });
    formElement.querySelectorAll('.text-red-500').forEach(error => {
        error.remove();
    });
};

// Manejo de filtros
document.addEventListener('DOMContentLoaded', function() {
    // Manejo de filtros
    const filterInputs = document.querySelectorAll('[data-filter]');
    filterInputs.forEach(input => {
        input.addEventListener('input', function() {
            const filterValue = this.value.toLowerCase();
            const targetClass = this.dataset.filter;
            const elements = document.querySelectorAll(`.${targetClass}`);

            elements.forEach(element => {
                const text = element.textContent.toLowerCase();
                element.style.display = text.includes(filterValue) ? '' : 'none';
            });
        });
    });

    // Inicializar validación de formularios
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            clearValidationErrors(this);
            handleFormSubmit(this);
        });
    });
});
