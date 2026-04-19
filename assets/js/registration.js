// Visitor Registration Form JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Set minimum date to today
    const visitDateInput = document.getElementById('visit_date');
    const today = new Date().toISOString().split('T')[0];
    visitDateInput.min = today;
    visitDateInput.value = today;

    // Form submission
    const visitorForm = document.getElementById('visitorForm');
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    const successModal = new bootstrap.Modal(document.getElementById('successModal'));

    visitorForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Show loading modal
        loadingModal.show();
        
        // Get form data
        const formData = new FormData(visitorForm);
        const data = {};
        formData.forEach((value, key) => {
            data[key] = value;
        });

        // Send registration request
        fetch('api/register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            loadingModal.hide();
            
            if (data.success) {
                successModal.show();
                visitorForm.reset();
                // Reset date to today
                visitDateInput.value = today;
            } else {
                showError(data.message || 'Registration failed. Please try again.');
            }
        })
        .catch(error => {
            loadingModal.hide();
            console.error('Error:', error);
            showError('An error occurred. Please try again later.');
        });
    });

    // Phone number validation
    const phoneInput = document.getElementById('phone');
    phoneInput.addEventListener('input', function() {
        const value = this.value.replace(/\D/g, '');
        if (value.length <= 10) {
            this.value = value;
        } else {
            this.value = value.substring(0, 10);
        }
    });

    // Email validation
    const emailInput = document.getElementById('email');
    emailInput.addEventListener('blur', function() {
        const email = this.value;
        if (email && !isValidEmail(email)) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });
});

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function showError(message) {
    // Create error alert
    const errorDiv = document.createElement('div');
    errorDiv.className = 'alert alert-danger alert-dismissible fade show';
    errorDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert at the top of the card body
    const cardBody = document.querySelector('.card-body');
    cardBody.insertBefore(errorDiv, cardBody.firstChild);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        errorDiv.remove();
    }, 5000);
}

function showSuccess(message) {
    // Create success alert
    const successDiv = document.createElement('div');
    successDiv.className = 'alert alert-success alert-dismissible fade show';
    successDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert at the top of the card body
    const cardBody = document.querySelector('.card-body');
    cardBody.insertBefore(successDiv, cardBody.firstChild);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        successDiv.remove();
    }, 5000);
}

// Form validation functions
function validateForm() {
    const form = document.getElementById('visitorForm');
    const inputs = form.querySelectorAll('input[required], textarea[required]');
    let isValid = true;

    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });

    return isValid;
}

// Auto-format phone number
function formatPhoneNumber(input) {
    const value = input.value.replace(/\D/g, '');
    const formattedValue = value.replace(/(\d{5})(\d{5})/, '$1-$2');
    input.value = formattedValue;
}

// ID proof validation based on type
function validateIdProof() {
    const idType = document.getElementById('id_proof_type').value;
    const idNumber = document.getElementById('id_proof_number').value;
    const idNumberInput = document.getElementById('id_proof_number');

    if (!idType || !idNumber) return true;

    let isValid = false;
    let minLength = 0;
    let maxLength = 0;

    switch(idType) {
        case 'aadhar':
            isValid = /^\d{12}$/.test(idNumber);
            break;
        case 'pan':
            isValid = /^[A-Z]{5}\d{4}[A-Z]{1}$/.test(idNumber.toUpperCase());
            break;
        case 'driving':
            isValid = /^[A-Z]{2}\d{2}\d{4}\d{7}$/.test(idNumber.toUpperCase());
            break;
        case 'passport':
            isValid = /^[A-Z]\d{7}$/.test(idNumber.toUpperCase());
            break;
        case 'voter':
            isValid = /^[A-Z]{3}\d{7}$/.test(idNumber.toUpperCase());
            break;
    }

    if (isValid) {
        idNumberInput.classList.remove('is-invalid');
        idNumberInput.classList.add('is-valid');
    } else {
        idNumberInput.classList.remove('is-valid');
        idNumberInput.classList.add('is-invalid');
    }

    return isValid;
}

// Add event listeners for ID proof validation
document.getElementById('id_proof_type').addEventListener('change', validateIdProof);
document.getElementById('id_proof_number').addEventListener('input', validateIdProof);
