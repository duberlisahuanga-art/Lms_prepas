/**
 * VALIDACIONES.JS - DEVIOZ ACADEMY
 * Funciones de validación reutilizables para formularios
 */

const Validaciones = {
    
    /**
     * Valida que un campo no esté vacío
     */
    requerido(valor, nombreCampo = 'Este campo') {
        if (!valor || valor.trim() === '') {
            return `${nombreCampo} es obligatorio`;
        }
        return null;
    },
    
    /**
     * Valida formato de email
     */
    email(valor) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!regex.test(valor)) {
            return 'El formato del correo no es válido';
        }
        return null;
    },
    
    /**
     * Valida longitud mínima
     */
    minLength(valor, min, nombreCampo = 'Este campo') {
        if (valor.length < min) {
            return `${nombreCampo} debe tener al menos ${min} caracteres`;
        }
        return null;
    },
    
    /**
     * Valida longitud máxima
     */
    maxLength(valor, max, nombreCampo = 'Este campo') {
        if (valor.length > max) {
            return `${nombreCampo} no debe exceder ${max} caracteres`;
        }
        return null;
    },
    
    /**
     * Valida que dos campos coincidan (ej: contraseñas)
     */
    coincide(valor1, valor2, mensaje = 'Los campos no coinciden') {
        if (valor1 !== valor2) {
            return mensaje;
        }
        return null;
    },
    
    /**
     * Valida que sea un número
     */
    esNumero(valor, nombreCampo = 'Este campo') {
        if (isNaN(valor) || valor.trim() === '') {
            return `${nombreCampo} debe ser un número`;
        }
        return null;
    },
    
    /**
     * Valida que sea un número positivo
     */
    numeroPositivo(valor, nombreCampo = 'Este campo') {
        const num = parseFloat(valor);
        if (isNaN(num) || num <= 0) {
            return `${nombreCampo} debe ser un número positivo`;
        }
        return null;
    },
    
    /**
     * Valida formato de teléfono (10 dígitos)
     */
    telefono(valor) {
        const regex = /^\d{10}$/;
        if (!regex.test(valor.replace(/\D/g, ''))) {
            return 'El teléfono debe tener 10 dígitos';
        }
        return null;
    },
    
    /**
     * Valida que solo contenga letras y espacios
     */
    soloLetras(valor, nombreCampo = 'Este campo') {
        const regex = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/;
        if (!regex.test(valor)) {
            return `${nombreCampo} solo debe contener letras`;
        }
        return null;
    },
    
    /**
     * Valida múltiples reglas y retorna el primer error
     */
    validar(valor, reglas) {
        for (const regla of reglas) {
            const error = regla(valor);
            if (error) return error;
        }
        return null;
    },
    
    /**
     * Muestra error en un elemento específico
     */
    mostrarError(elemento, mensaje) {
        if (!elemento) return;
        elemento.textContent = mensaje;
        elemento.style.display = 'block';
        elemento.style.color = '#DC2626';
        elemento.style.fontSize = '0.85rem';
        elemento.style.marginTop = '5px';
    },
    
    /**
     * Limpia errores de un elemento
     */
    limpiarError(elemento) {
        if (!elemento) return;
        elemento.textContent = '';
        elemento.style.display = 'none';
    },
    
    /**
     * Valida un formulario completo según reglas definidas
     */
    validarFormulario(formulario, reglas) {
        const errores = [];
        
        for (const [campo, campoReglas] of Object.entries(reglas)) {
            const input = formulario.querySelector(`[name="${campo}"], #${campo}`);
            if (!input) continue;
            
            const valor = input.value;
            const error = this.validar(valor, campoReglas);
            
            if (error) {
                errores.push(error);
                const errorElement = document.getElementById(`error-${campo}`);
                if (errorElement) {
                    this.mostrarError(errorElement, error);
                }
            } else {
                const errorElement = document.getElementById(`error-${campo}`);
                if (errorElement) {
                    this.limpiarError(errorElement);
                }
            }
        }
        
        return errores.length === 0 ? null : errores;
    }
};

// Hacer Validaciones disponible globalmente
window.Validaciones = Validaciones;

// Ejemplo de uso:
/*
const reglas = {
    nombre: [
        (v) => Validaciones.requerido(v, 'El nombre'),
        (v) => Validaciones.soloLetras(v, 'El nombre'),
        (v) => Validaciones.minLength(v, 3, 'El nombre')
    ],
    email: [
        (v) => Validaciones.requerido(v, 'El email'),
        (v) => Validaciones.email(v)
    ],
    password: [
        (v) => Validaciones.requerido(v, 'La contraseña'),
        (v) => Validaciones.minLength(v, 6, 'La contraseña')
    ]
};

const errores = Validaciones.validarFormulario(document.getElementById('miForm'), reglas);
if (errores) {
    console.log('Errores:', errores);
} else {
    // Enviar formulario
}
*/