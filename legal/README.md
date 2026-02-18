# 📘 Sistema Dinámico de Contratos – Valírica

Este documento describe cómo funciona la generación dinámica de contratos SaaS para Valírica.

El sistema debe permitir:

- Generar contrato versión España o Colombia automáticamente.
- Personalizar variables dinámicas.
- Hacer tracking contractual.
- Mantener integridad de datos originales en tabla `usuarios`.
- Separar datos contractuales de datos de login.

---

# 1️⃣ Variables Dinámicas del Contrato

## {{FECHA_REGISTRO}}

- Fuente: tabla `usuarios`
- Columna: `fecha_registro`
- Tipo: DATETIME
- Uso: Fecha oficial de inicio contractual.
- Regla: El contrato inicia desde el registro en la plataforma, no desde la firma.

---

## {{FECHA_ACEPTACION}}

- Fuente: nueva tabla `contratos_empresas`
- Campo: `fecha_aceptacion`
- Tipo: DATETIME
- Se registra en el momento de firma electrónica.

---

## {{PERIODO_PRUEBA}}

- Fuente: nueva tabla `contratos_empresas`
- Campo: `periodo_prueba_dias`
- Tipo: INT
- Default: 15 días
- Puede modificarse manualmente antes de la firma.

---

## {{TIPO_PLAN}}

- Fuente: nueva tabla `contratos_empresas`
- Campo: `tipo_plan`
- Tipo: ENUM('mensual','anual')

---

## {{PRECIO_PLAN}}

El precio depende de:

- País de la empresa
- Tipo de plan (mensual / anual)

### País se determina desde:

- Tabla: `cultura_ideal`
- Columna: `ubicacion`
- Relación: `cultura_ideal.usuario_id = usuarios.id`

---

### Reglas de precio

#### ESPAÑA

Mensual:
€6 por empleado activo / mes (pago anticipado)

Anual:
€5.25 por empleado / mes
Facturación anual anticipada

---

#### COLOMBIA

Mensual:
$20.000 COP + IVA por empleado / mes

Anual:
$17.000 COP + IVA por empleado / mes
Facturación anual anticipada

---

El sistema debe generar el texto dinámicamente según:

IF ubicacion = "España"
    aplicar precios España
ELSE
    aplicar precios Colombia

---

## {{EMPRESA_CLIENTE}}

- Fuente: tabla `usuarios`
- Columna: `empresa`
- También puede incluir:
  - Logo: tabla `usuarios`, columna `logo`

El logo puede insertarse dinámicamente en el HTML del contrato.

---

## {{CIF_CLIENTE}} / {{NIT_CLIENTE}}

- Debe almacenarse en nueva tabla `contratos_empresas`
- No se encuentra en tabla `usuarios`
- Se solicita al momento de configuración contractual.

---

## {{REPRESENTANTE}}

- Fuente base: tabla `usuarios`
  - Columnas: `nombre`, `apellido`
- Debe preguntarse antes de generar contrato si:
  - Se mantiene
  - Se actualiza solo para efectos contractuales

IMPORTANTE:
Si se actualiza, el nuevo nombre NO modifica la tabla `usuarios`.

Debe guardarse en `contratos_empresas.representante_legal`.

---

## {{EMAIL_CLIENTE}}

- Fuente base: tabla `usuarios`
- Columna: `email`

Debe permitirse modificar únicamente para efectos contractuales.
No debe alterar el email de login.

Se almacena copia en `contratos_empresas.email_contrato`.

---

## {{EMAIL_FACTURACION_ADICIONAL}}

- Campo opcional.
- Si existe, debe mostrarse claramente como:
  "Correo de facturación adicional"
- Si no existe, el contrato no debe mostrar vacío visible.
- Se almacena en `contratos_empresas.email_facturacion`.

---

# 2️⃣ Nueva Tabla SQL – contratos_empresas

Debe crearse la siguiente tabla:

```sql
CREATE TABLE contratos_empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    pais VARCHAR(50) NOT NULL,
    periodo_prueba_dias INT DEFAULT 15,
    tipo_plan ENUM('mensual','anual') NOT NULL,
    precio_base DECIMAL(10,2) NOT NULL,
    representante_legal VARCHAR(255),
    identificacion_fiscal VARCHAR(100),
    email_contrato VARCHAR(255),
    email_facturacion VARCHAR(255),
    fecha_registro DATETIME,
    fecha_aceptacion DATETIME,
    estado ENUM('pendiente','activo','cancelado') DEFAULT 'pendiente',
    version_contrato VARCHAR(10) DEFAULT '1.1',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);
