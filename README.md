# LMS Prepa - Plataforma E-Learning con IA Tutor

## Descripción

**LMS Prepa** es una plataforma e-learning orientada a la formación y acompañamiento de estudiantes mediante la integración de un **Tutor basado en Inteligencia Artificial**. El sistema busca centralizar contenidos educativos, cursos, materiales y seguimiento del aprendizaje, permitiendo que la IA responda consultas, explique conceptos y apoye al estudiante durante su proceso formativo.

## Objetivo

Desarrollar una plataforma LMS que combine la gestión del aprendizaje con Inteligencia Artificial para ofrecer una experiencia educativa interactiva y personalizada.

El sistema permite o contempla:

- Gestión de usuarios y estudiantes.
- Administración de cursos y contenidos.
- Materiales educativos.
- Seguimiento del aprendizaje.
- Tutor virtual mediante Inteligencia Artificial.
- Generación de explicaciones y respuestas educativas.
- Integración con modelos LLM.
- Consulta de información almacenada en el LMS.
- Implementación futura de RAG.
- Búsqueda complementaria de información en Internet.
- Generación de ejercicios y cuestionarios.
- Recomendación personalizada de contenidos.

## Tecnologías

### Frontend
- HTML5
- CSS3
- JavaScript
- Bootstrap

### Backend
- PHP

### Base de datos
- MySQL / MariaDB

### Inteligencia Artificial
- Gemini
- Groq
- Modelos LLM

### Herramientas
- XAMPP
- Git
- GitHub
- Composer

## Estructura del proyecto

```text
Lms_prepa/
├── backend/
│   ├── config/
│   ├── controllers/
│   ├── models/
│   └── services/
├── database/
├── frontend/
├── uploads/
├── vendor/
├── .gitignore
└── README.md
```

## IA Tutor

El proyecto incorpora un **IA Tutor** que funciona como asistente educativo para apoyar al estudiante.

Entre sus principales capacidades se encuentran:

- Responder preguntas.
- Explicar conceptos.
- Generar ejemplos.
- Resumir contenidos.
- Orientar al estudiante.
- Resolver dudas académicas.
- Generar ejercicios.
- Apoyar el aprendizaje mediante modelos de IA.

La arquitectura general es:

```text
Estudiante
    ↓
Frontend LMS
    ↓
Backend PHP
    ↓
IA Tutor
    ↓
Gemini / Groq / LLM
    ↓
Respuesta educativa
```

## Integración futura con RAG

El sistema está preparado para evolucionar hacia una arquitectura **RAG (Retrieval Augmented Generation)**, permitiendo que la IA utilice directamente los contenidos educativos almacenados en el LMS.

```text
Documentos / Cursos / Materiales
              ↓
       Extracción de texto
              ↓
          Embeddings
              ↓
       Base vectorial
              ↓
           IA Tutor
              ↓
   Respuesta contextualizada
```

Esto permitirá que las respuestas del Tutor IA estén relacionadas directamente con el material educativo disponible para el estudiante.

## Búsqueda en Internet

También se contempla incorporar búsqueda web para complementar información cuando sea necesario.

El flujo esperado será:

```text
Pregunta del estudiante
        ↓
Buscar información en LMS
        ↓
¿Información suficiente?
      ↓       ↓
     Sí       No
      ↓       ↓
Responder   Buscar Internet
                ↓
          Analizar fuentes
                ↓
          Generar respuesta
```

De esta manera, el sistema podrá diferenciar entre información proveniente del LMS y fuentes externas.

## Seguridad

Las credenciales y API Keys no deben almacenarse en GitHub.

Los archivos sensibles se excluyen mediante `.gitignore`, por ejemplo:

```gitignore
vendor/
node_modules/

.env
.env.*
!.env.example

backend/config/gemini_free.php
backend/config/llama_free.php

*.log
.vscode/
.idea/
```

Las API Keys de Gemini y Groq deben configurarse únicamente en el entorno local o mediante variables de entorno.

## Instalación

Clonar el repositorio:

```bash
git clone https://github.com/duberlisahuanga-art/Lms_prepas.git
```

Ingresar al proyecto:

```bash
cd Lms_prepas
```

Colocar el proyecto dentro de:

```text
C:\xampp\htdocs\
```

Iniciar desde XAMPP:

```text
Apache
MySQL
```

Crear o importar la base de datos utilizando los archivos disponibles dentro de:

```text
database/
```

Configurar localmente las credenciales necesarias para los servicios de Inteligencia Artificial.

## Próximas mejoras

- Integración completa del IA Tutor con el LMS.
- Implementación de RAG.
- Base de datos vectorial.
- Búsqueda inteligente de materiales.
- Búsqueda en Internet.
- Citación de fuentes.
- Generación automática de cuestionarios.
- Generación de ejercicios.
- Planes de estudio personalizados.
- Seguimiento del progreso.
- Recomendación de contenidos.
- Historial de conversaciones.
- Evaluación de respuestas del estudiante.
- Panel administrativo de IA.

## Repositorio

GitHub:

```text
https://github.com/duberlisahuanga-art/Lms_prepas
```

## Autor

**Duberli Sahuanga**

## Estado

Proyecto actualmente en desarrollo y mejora continua, orientado a integrar un **LMS con un Tutor basado en Inteligencia Artificial**.

## Licencia

Proyecto desarrollado con fines educativos, académicos y de investigación.
