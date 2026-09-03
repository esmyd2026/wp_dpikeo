# Refactorización incremental del chatbot a arquitectura multiempresa con WhatsApp Embedded Signup

## 1. Objetivo general

Adaptar el chatbot actual, que hoy funciona para una sola empresa, para que pueda trabajar con múltiples empresas sin reescribir ni alterar innecesariamente la lógica existente.

La prioridad es conservar los flujos, menús, reglas, respuestas, integraciones y comportamiento actual del chatbot, agregando únicamente una capa multiempresa y un mecanismo estándar para conectar números de WhatsApp Business mediante **Meta Embedded Signup**.

El primer caso real de implementación será **dpikeo**.

Actualmente:

- El chatbot de dpikeo ya está desarrollado.
- Está desplegado en Google Cloud.
- Todavía no está conectado al número oficial de WhatsApp de dpikeo.
- Se está utilizando un número de prueba de Meta.
- Las credenciales de WhatsApp se encuentran actualmente en variables de entorno.
- La lógica fue desarrollada pensando inicialmente en una sola empresa.

El objetivo es que dpikeo sea el primer cliente que utilice la nueva arquitectura y que, posteriormente, incorporar otro cliente no requiera modificar código.

---

# 2. Principio fundamental

## NO reescribir el chatbot

No se debe reconstruir el chatbot desde cero.

No modificar innecesariamente:

- Flujos conversacionales.
- Menús.
- Respuestas.
- Reglas de negocio.
- Integraciones existentes.
- Servicios que ya funcionan correctamente.
- Lógica particular de dpikeo.

El cambio debe ser **incremental**.

La idea es introducir una capa que permita saber:

> “¿Para qué empresa estoy procesando este mensaje?”

y, a partir de esa empresa, cargar automáticamente:

- Credenciales de WhatsApp.
- Configuración del chatbot.
- Datos comerciales.
- Configuración del cliente.
- Integraciones específicas.
- Webhooks o parámetros necesarios.

---

# 3. Arquitectura esperada

La entidad principal debe ser una **Empresa**.

Nunca utilizar como identificador principal:

- Número de teléfono.
- Nombre comercial.
- Dominio.
- WABA ID.

Debe existir un identificador interno propio.

Ejemplo:

```text
Empresa
ID: 1
Nombre: dpikeo
Slug: dpikeo
Estado: activo
```

Posteriormente:

```text
Empresa ID 2
Nombre: Zapatos XYZ
Slug: zapatos-xyz
Estado: activo
```

El número de WhatsApp debe estar relacionado con la empresa, pero no debe ser la empresa.

---

# 4. Estructura mínima de datos

Se recomienda agregar las siguientes tablas o modelos, adaptándolos a la tecnología actual del proyecto.

## 4.1. companies / empresas

```text
id
uuid
name
slug
status
created_at
updated_at
```

Opcionales:

```text
legal_name
ruc
email
phone
logo
timezone
country
metadata
```

El `uuid` es recomendable para evitar exponer IDs secuenciales hacia el frontend.

---

## 4.2. whatsapp_accounts

Cada empresa puede tener uno o varios números en el futuro.

```text
id
company_id
waba_id
phone_number_id
display_phone_number
verified_name
access_token_encrypted
token_expires_at
status
quality_rating
webhook_status
created_at
updated_at
```

Relación:

```text
Empresa 1 ---- N WhatsAppAccount
```

Aunque inicialmente cada empresa tenga un único número, diseñar la relación como **uno a muchos**.

---

## 4.3. company_settings

Se puede utilizar para configuraciones particulares de cada empresa.

```text
id
company_id
key
value
created_at
updated_at
```

O utilizar una columna JSON:

```text
settings
```

Ejemplos:

```json
{
  "bot_name": "Asistente dpikeo",
  "currency": "USD",
  "timezone": "America/Guayaquil",
  "welcome_message_enabled": true
}
```

---

# 5. No utilizar el número de teléfono como company_id

Cuando llegue un webhook de Meta, utilizar principalmente el:

```text
phone_number_id
```

para identificar la cuenta de WhatsApp.

Flujo:

```text
Meta
  ↓
Webhook
  ↓
phone_number_id
  ↓
Buscar whatsapp_accounts
  ↓
Obtener company_id
  ↓
Cargar empresa
  ↓
Procesar mensaje con la lógica correspondiente
```

Ejemplo:

```text
phone_number_id = 123456789
        ↓
whatsapp_accounts
        ↓
company_id = 1
        ↓
dpikeo
```

A partir de ese momento todo el procesamiento debe ejecutarse dentro del contexto de dpikeo.

---

# 6. Crear un Company Context

Agregar una capa central que permita que los servicios sepan qué empresa está siendo procesada.

Conceptualmente:

```text
CompanyContext
```

Debe contener como mínimo:

```text
company_id
company
whatsapp_account
```

Flujo recomendado:

```text
Webhook entrante
      ↓
Resolver empresa
      ↓
Crear CompanyContext
      ↓
Ejecutar chatbot actual
```

De esta forma no es necesario cambiar profundamente la lógica del chatbot.

---

# 7. Refactorización de credenciales de WhatsApp

Actualmente seguramente existe algo similar a:

```env
WHATSAPP_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_BUSINESS_ACCOUNT_ID=
```

Esto debe dejar de ser la fuente principal de configuración.

## Nueva estrategia

Los servicios deben recibir la cuenta correspondiente.

Ejemplo conceptual:

```text
WhatsAppService.sendMessage(
    whatsappAccount,
    recipient,
    message
)
```

o:

```text
WhatsAppService(companyContext)
```

y el servicio obtiene:

```text
phone_number_id
access_token
waba_id
```

desde la base de datos.

---

# 8. Migración progresiva sin romper producción

Durante la primera etapa puede mantenerse temporalmente un fallback.

Ejemplo:

```text
1. Buscar configuración por company_id.
2. Si existe, utilizar base de datos.
3. Si todavía no existe, utilizar ENV.
```

Esto es únicamente para facilitar la migración.

Cuando dpikeo funcione correctamente mediante la nueva arquitectura:

> eliminar el fallback del ENV.

Las credenciales reales de clientes no deben depender permanentemente de variables de entorno.

---

# 9. Seguridad de tokens

Los Access Tokens de Meta son secretos.

Por lo tanto:

- Nunca guardarlos en texto plano.
- Nunca enviarlos al frontend.
- Nunca mostrarlos completos en logs.
- Nunca incluirlos en respuestas API.
- Nunca subirlos a Git.
- Nunca almacenarlos directamente en JavaScript del navegador.

El token debe ser cifrado antes de guardarse.

Ejemplo conceptual:

```text
access_token_encrypted
```

El backend lo descifra únicamente cuando necesita comunicarse con Graph API.

---

# 10. Embedded Signup

Implementar **Meta WhatsApp Embedded Signup** como mecanismo para incorporar números de clientes.

El objetivo es evitar el procedimiento manual de:

```text
copiar token
copiar phone_number_id
copiar WABA ID
editar .env
reiniciar servidor
```

El usuario debe poder realizar algo parecido a:

```text
Entrar al panel
    ↓
Empresa: dpikeo
    ↓
Configuración
    ↓
WhatsApp
    ↓
Conectar WhatsApp Business
```

Luego:

```text
Meta Embedded Signup
    ↓
Cliente inicia sesión
    ↓
Selecciona/crea Business Portfolio
    ↓
Selecciona/crea WABA
    ↓
Registra número
    ↓
Verifica número
    ↓
Autoriza Siglo Tecnológico
    ↓
Backend recibe información
    ↓
Se guarda automáticamente
```

---

# 11. Micrositio para conectar WhatsApp

Crear dentro de la plataforma un módulo o micrositio propio de **Siglo Tecnológico**.

Ejemplo recomendado:

```text
https://app.siglotecnologico.com
```

o:

```text
https://panel.siglotecnologico.com
```

Dentro:

```text
/whatsapp/connect
```

Ejemplo:

```text
https://app.siglotecnologico.com/whatsapp/connect
```

No crear un dominio diferente para cada empresa únicamente para Embedded Signup.

---

# 12. Importante sobre los dominios

Siglo Tecnológico será el proveedor tecnológico.

Por lo tanto, el Embedded Signup debe ejecutarse desde un dominio controlado por Siglo Tecnológico.

Ejemplo:

```text
app.siglotecnologico.com
```

Aunque mañana el cliente sea:

```text
dpikeo.com
zapatos.com
restaurantemaria.com
empresaabc.com
```

no es necesario ejecutar el Embedded Signup desde esos dominios.

Los clientes pueden conectarse desde:

```text
app.siglotecnologico.com
```

Ejemplo:

```text
app.siglotecnologico.com/empresa/dpikeo/whatsapp
```

o:

```text
app.siglotecnologico.com/settings/whatsapp
```

Meta necesita reconocer/autorizAR el dominio desde el cual se ejecuta el flujo de conexión, no necesariamente el dominio comercial del cliente.

---

# 13. No amarrar la arquitectura al dominio del cliente

La plataforma no debe asumir:

```text
dominio = empresa
```

Una empresa puede:

- No tener página web.
- Cambiar de dominio.
- Tener varios dominios.
- Utilizar únicamente redes sociales.

La relación correcta debe ser:

```text
Empresa
   ↓
WhatsApp Account
```

No:

```text
Dominio
   ↓
WhatsApp
```

---

# 14. Panel administrativo

Agregar una sección:

```text
Configuración
   └── WhatsApp Business
```

Debe mostrar como mínimo:

### Estado

```text
No conectado
Conectando
Conectado
Error
Desconectado
Requiere acción
```

### Datos visibles

```text
Número
Nombre verificado
WABA ID
Phone Number ID
Estado
Fecha de conexión
```

No mostrar el access token.

---

# 15. Botones del módulo

Agregar inicialmente:

```text
Conectar WhatsApp
Reconectar
Actualizar estado
Desconectar
```

Opcional:

```text
Probar conexión
Enviar mensaje de prueba
```

---

# 16. Flujo técnico de conexión

Cuando el usuario pulse:

```text
Conectar WhatsApp
```

se inicia Embedded Signup.

Al completar el proceso, el frontend debe entregar al backend únicamente la información necesaria para finalizar la autorización.

El backend será responsable de:

```text
1. Validar la respuesta.
2. Obtener/intercambiar las credenciales necesarias.
3. Obtener WABA ID.
4. Obtener Phone Number ID.
5. Obtener información del número.
6. Relacionar la cuenta con company_id.
7. Guardar tokens cifrados.
8. Validar suscripción/webhook.
9. Marcar cuenta como activa.
```

---

# 17. Webhook multiempresa

Idealmente debe existir **un único endpoint principal de webhook** para toda la plataforma.

Ejemplo:

```text
https://api.siglotecnologico.com/webhooks/meta/whatsapp
```

No crear necesariamente:

```text
/webhook/dpikeo
/webhook/zapatos
/webhook/restaurante
```

El webhook recibe el evento y determina el cliente mediante:

```text
phone_number_id
```

---

# 18. Flujo del webhook

```text
Meta
   ↓
POST /webhooks/meta/whatsapp
   ↓
Leer metadata.phone_number_id
   ↓
Buscar whatsapp_accounts.phone_number_id
   ↓
Obtener company_id
   ↓
Crear CompanyContext
   ↓
Ejecutar MessageHandler actual
   ↓
Responder usando credenciales de esa cuenta
```

---

# 19. Mantener el MessageHandler actual

No duplicar innecesariamente:

```text
MessageHandlerdpikeo
MessageHandlerZapatos
MessageHandlerCliente3
```

El handler debe ser reutilizable.

La lógica puede cargar configuración en función del cliente.

Ejemplo:

```text
MessageHandler.handle(message, companyContext)
```

---

# 20. Configuración específica del chatbot por empresa

La arquitectura debe permitir posteriormente tener:

```text
company_id = 1
bot_type = dpikeo
```

```text
company_id = 2
bot_type = ecommerce
```

```text
company_id = 3
bot_type = cobranza
```

La prioridad actual NO es reconstruir todos los flujos para que sean dinámicos.

Primero:

> hacer que el chatbot actual de dpikeo funcione con contexto multiempresa.

Después se podrá generalizar la lógica comercial.

---

# 21. Evitar duplicar servidor por cliente como requisito técnico

Aunque comercialmente se decida aislar un cliente, la aplicación debe quedar preparada para ejecutar varias empresas en una misma instancia.

No dejar como requisito:

```text
1 cliente = 1 copia de código
```

La arquitectura debe soportar:

```text
1 aplicación
N empresas
N WhatsApp Accounts
```

Si en algún momento se desea desplegar instancias separadas por razones comerciales o contractuales, debe poder hacerse sin modificar la lógica.

---

# 22. Primer ejercicio real: dpikeo

dpikeo debe utilizarse como prueba piloto.

## Estado inicial

```text
Empresa: dpikeo
Chatbot: desarrollado
Servidor: Google Cloud
WhatsApp actual: número de prueba
Número oficial: pendiente de entrega
```

---

# 23. Paso 1 — Crear dpikeo como empresa

Agregar:

```text
company_id = 1
name = dpikeo
slug = dpikeo
status = active
```

Migrar la configuración actual de dpikeo para que quede asociada a:

```text
company_id = 1
```

---

# 24. Paso 2 — Mantener el número de prueba

Mientras el cliente entrega su número real, registrar el número de prueba como una cuenta asociada a dpikeo.

Ejemplo:

```text
whatsapp_account
company_id: 1
phone_number_id: PHONE_NUMBER_ID_PRUEBA
status: test
```

Esto permitirá probar la arquitectura antes de conectar el número oficial.

---

# 25. Paso 3 — Implementar Embedded Signup

Crear en el panel:

```text
dpikeo
  ↓
Configuración
  ↓
WhatsApp
  ↓
Conectar número
```

El botón debe abrir la configuración de Embedded Signup creada en la app de Meta de Siglo Tecnológico.

---

# 26. Paso 4 — Cuando dpikeo entregue el número

El proceso debe ser:

```text
1. Abrir panel dpikeo.
2. Pulsar “Conectar WhatsApp”.
3. Abrir flujo Meta.
4. Cliente inicia sesión.
5. Seleccionar su negocio.
6. Seleccionar/crear WABA.
7. Agregar número.
8. Verificar mediante SMS o llamada según disponibilidad de Meta.
9. Finalizar autorización.
10. Backend guarda automáticamente la cuenta.
```

No debería ser necesario modificar:

```text
.env
código
servidor
rutas
MessageHandler
```

---

# 27. Resultado esperado para dpikeo

Después del proceso:

```text
dpikeo
│
├── company_id: 1
│
├── whatsapp_account
│      ├── WABA_ID
│      ├── PHONE_NUMBER_ID
│      ├── número
│      ├── token cifrado
│      └── activo
│
└── chatbot
       └── lógica actual
```

Cuando alguien escriba al número:

```text
Meta
 ↓
Webhook Siglo Tecnológico
 ↓
Phone Number ID
 ↓
dpikeo
 ↓
Chatbot dpikeo
 ↓
Respuesta
```

---

# 28. Segundo cliente

Después de validar dpikeo:

```text
Crear empresa
   ↓
Conectar WhatsApp
   ↓
Embedded Signup
   ↓
Guardar configuración
   ↓
Activar chatbot
```

No modificar código fuente.

No cambiar `.env`.

No crear manualmente tokens.

---

# 29. Ejemplo futuro

```text
Siglo Tecnológico SaaS
│
├── Empresa 1: dpikeo
│      └── WhatsApp: +593...
│
├── Empresa 2: Zapatos XYZ
│      └── WhatsApp: +593...
│
├── Empresa 3: Restaurante ABC
│      └── WhatsApp: +593...
│
└── Empresa 4: Clínica XYZ
       └── WhatsApp: +593...
```

Todos pueden utilizar:

```text
api.siglotecnologico.com
```

y:

```text
app.siglotecnologico.com
```

---

# 30. Servicios sugeridos

Crear o adaptar servicios equivalentes a:

```text
CompanyService
CompanyContext
WhatsAppAccountService
WhatsAppCredentialService
MetaEmbeddedSignupService
MetaGraphService
WhatsAppWebhookService
```

El nombre real puede adaptarse al lenguaje y arquitectura actual.

---

# 31. WhatsAppCredentialService

Debe ser el único responsable de obtener credenciales.

Ejemplo conceptual:

```text
getCredentials(company_id)
```

Devuelve internamente:

```text
phone_number_id
waba_id
access_token
```

Esto evita que diferentes partes del proyecto accedan directamente a ENV o base de datos.

---

# 32. WhatsAppWebhookService

Responsabilidades:

```text
validar webhook
parsear payload
extraer phone_number_id
resolver empresa
crear CompanyContext
enviar evento al MessageHandler
```

---

# 33. MetaGraphService

Centralizar llamadas hacia Meta Graph API.

Ejemplos:

```text
sendText()
sendTemplate()
sendInteractive()
sendMedia()
markAsRead()
getPhoneNumber()
getWaba()
```

Nunca repartir peticiones HTTP a Graph API por todo el código.

---

# 34. Migración del servicio actual

Si actualmente existe algo equivalente a:

```javascript
const token = process.env.WHATSAPP_TOKEN;
const phoneId = process.env.PHONE_NUMBER_ID;
```

debe evolucionar conceptualmente hacia:

```javascript
const credentials =
    await whatsappCredentialService.getByCompany(companyId);
```

y posteriormente:

```javascript
await whatsappService.sendMessage(
    credentials,
    destination,
    payload
);
```

---

# 35. Identificación de empresa en mensajes salientes

Toda operación de envío debe conocer:

```text
company_id
```

o:

```text
WhatsAppAccount
```

Nunca enviar mensajes utilizando una credencial global.

---

# 36. Sesiones del chatbot

Si actualmente las sesiones se manejan solamente por:

```text
senderId
```

cambiar la clave lógica a:

```text
company_id + senderId
```

Ejemplo:

```text
1:593999999999
```

Esto evita colisiones.

Porque una misma persona podría escribirle a:

```text
dpikeo
```

y también a:

```text
Zapatos XYZ
```

y deben ser conversaciones independientes.

---

# 37. Caché

Si se utiliza:

```text
Redis
Memory
Firestore
Cache
```

las claves también deben incluir:

```text
company_id
```

Ejemplo:

```text
chatbot:1:session:593999999999
```

---

# 38. Logs

Todos los logs deberían incluir:

```text
company_id
company_name
phone_number_id
sender_id
```

Ejemplo:

```text
[INFO]
Company: 1 - dpikeo
PhoneNumberID: 123456
Sender: 593999999999
Action: Incoming message
```

Nunca imprimir tokens.

---

# 39. Base de datos y aislamiento

Todas las tablas que almacenen datos pertenecientes a un cliente deben poder quedar relacionadas con:

```text
company_id
```

Ejemplos futuros:

```text
customers
orders
bot_sessions
bot_logs
conversations
messages
configurations
catalogs
payments
appointments
```

No es obligatorio migrar todo inmediatamente si eso pone en riesgo la lógica actual.

La migración puede ser progresiva.

---

# 40. Restricción crítica

Nunca confiar en un:

```text
company_id
```

enviado libremente por el frontend para acceder a datos.

El backend debe validar que:

```text
usuario
 ↓
pertenece/tiene acceso
 ↓
empresa
```

---

# 41. Usuario administrador de Siglo Tecnológico

Debe existir la posibilidad de que Siglo Tecnológico pueda administrar:

```text
todas las empresas
```

Mientras que un cliente solo pueda administrar:

```text
su empresa
```

Preparar la arquitectura aunque inicialmente el panel tenga pocos usuarios.

---

# 42. Checklist de implementación

## Fase 1 — Auditoría

- [ ] Identificar todas las variables ENV relacionadas con WhatsApp.
- [ ] Localizar todos los servicios que utilizan token.
- [ ] Localizar todos los servicios que utilizan Phone Number ID.
- [ ] Localizar la recepción actual del webhook.
- [ ] Revisar cómo se almacenan las sesiones.
- [ ] Revisar cómo se almacenan configuraciones.
- [ ] Documentar flujo actual antes de modificar.

---

## Fase 2 — Multiempresa

- [ ] Crear tabla `companies`.
- [ ] Crear tabla `whatsapp_accounts`.
- [ ] Crear relación empresa → WhatsApp Accounts.
- [ ] Crear dpikeo como `company_id = 1`.
- [ ] Crear `CompanyContext`.
- [ ] Agregar resolución de empresa por Phone Number ID.
- [ ] Adaptar sesiones para incluir company_id.
- [ ] Adaptar logs para incluir company_id.

---

## Fase 3 — Credenciales

- [ ] Crear `WhatsAppCredentialService`.
- [ ] Mover acceso a credenciales hacia ese servicio.
- [ ] Cifrar access tokens.
- [ ] Evitar exposición de tokens.
- [ ] Mantener ENV únicamente como fallback temporal.
- [ ] Probar el chatbot actual sin cambiar su lógica.

---

## Fase 4 — Micrositio/panel

- [ ] Crear módulo `Configuración → WhatsApp`.
- [ ] Mostrar estado de conexión.
- [ ] Crear botón `Conectar WhatsApp`.
- [ ] Crear botón `Reconectar`.
- [ ] Crear botón `Desconectar`.
- [ ] Crear vista de información de la cuenta.
- [ ] Nunca mostrar token.

---

## Fase 5 — Embedded Signup

- [ ] Integrar configuración Embedded Signup de Meta.
- [ ] Ejecutar flujo desde dominio de Siglo Tecnológico.
- [ ] Recibir resultado en frontend.
- [ ] Enviar datos necesarios al backend.
- [ ] Completar intercambio/autorización desde backend.
- [ ] Obtener WABA ID.
- [ ] Obtener Phone Number ID.
- [ ] Guardar credenciales cifradas.
- [ ] Asociar la cuenta a company_id.
- [ ] Consultar estado del número.
- [ ] Validar webhook.

---

## Fase 6 — Webhook multiempresa

- [ ] Mantener un endpoint central.
- [ ] Validar webhook.
- [ ] Extraer Phone Number ID.
- [ ] Resolver WhatsAppAccount.
- [ ] Obtener empresa.
- [ ] Crear CompanyContext.
- [ ] Ejecutar MessageHandler existente.
- [ ] Responder usando la cuenta correspondiente.

---

## Fase 7 — Prueba con número de Meta

- [ ] Asociar número de prueba a dpikeo.
- [ ] Enviar mensaje.
- [ ] Confirmar company_id correcto.
- [ ] Confirmar sesión.
- [ ] Confirmar respuesta.
- [ ] Confirmar logs.
- [ ] Confirmar que los flujos existentes siguen funcionando.

---

## Fase 8 — Número oficial de dpikeo

Cuando dpikeo entregue el número:

- [ ] Ejecutar Embedded Signup.
- [ ] Registrar/verificar el número.
- [ ] Guardar WABA ID.
- [ ] Guardar Phone Number ID.
- [ ] Guardar token cifrado.
- [ ] Validar webhook.
- [ ] Enviar mensaje de prueba.
- [ ] Recibir mensaje real.
- [ ] Ejecutar flujo completo.
- [ ] Confirmar operación sin editar ENV.

---

## Fase 9 — Segundo cliente

- [ ] Crear empresa nueva.
- [ ] Conectar número mediante Embedded Signup.
- [ ] Validar que no sea necesario modificar código.
- [ ] Validar aislamiento de sesiones.
- [ ] Validar aislamiento de configuración.
- [ ] Validar aislamiento de mensajes.

---

# 43. Criterios de aceptación

El desarrollo se considerará exitoso cuando se pueda hacer lo siguiente:

### Caso dpikeo

```text
Crear/seleccionar dpikeo
→ Conectar WhatsApp
→ Completar Meta Embedded Signup
→ Guardar configuración automáticamente
→ Recibir mensaje
→ Resolver que pertenece a dpikeo
→ Ejecutar chatbot actual
→ Responder
```

sin:

```text
editar código
editar .env
copiar token manual
copiar Phone Number ID manual
reiniciar despliegue para cambiar cliente
```

---

# 44. Criterio para un segundo cliente

Debe poder hacerse:

```text
Crear Empresa B
→ Conectar WhatsApp
→ Completar Meta
→ Activar
```

sin afectar dpikeo.

---

# 45. Qué NO hacer

No:

- Reescribir todo el chatbot.
- Duplicar la lógica por empresa.
- Utilizar el teléfono como ID de empresa.
- Utilizar el dominio como ID de empresa.
- Guardar tokens sin cifrar.
- Exponer tokens en frontend.
- Crear un `.env` diferente como mecanismo principal por cliente.
- Crear un webhook diferente obligatoriamente por cliente.
- Amarrar la empresa a un dominio web.
- Romper el chatbot actual para lograr multiempresa.

---

# 46. Prioridad del desarrollo

El orden correcto es:

```text
1. Mantener dpikeo funcionando.
2. Introducir Company.
3. Introducir WhatsAppAccount.
4. Resolver empresa por Phone Number ID.
5. Centralizar credenciales.
6. Convertir webhook en multiempresa.
7. Implementar Embedded Signup.
8. Probar con número de prueba.
9. Conectar número oficial de dpikeo.
10. Probar incorporación de segundo cliente.
```

---

# 47. Resultado final esperado

La arquitectura debe quedar preparada de esta manera:

```text
                  META
                    │
                    │
              WhatsApp Cloud API
                    │
                    ▼
        api.siglotecnologico.com
                    │
            Webhook central
                    │
                    ▼
        Resolver Phone Number ID
                    │
                    ▼
              WhatsAppAccount
                    │
                    ▼
                 Empresa
                    │
                    ▼
              CompanyContext
                    │
                    ▼
             Chatbot actual
                    │
                    ▼
             Lógica del cliente
```

Panel:

```text
app.siglotecnologico.com
        │
        ├── Empresas
        │
        ├── dpikeo
        │      └── WhatsApp
        │            └── Conectar
        │
        └── Cliente futuro
               └── WhatsApp
                     └── Conectar
```

---

# 48. Instrucción final para el asistente de desarrollo

> Trabajar sobre la plataforma existente. No reconstruir el chatbot ni cambiar su lógica actual salvo cuando sea estrictamente necesario para introducir el contexto de empresa.
>
> La prioridad es desacoplar las credenciales globales de WhatsApp del chatbot y hacer que cada mensaje sea procesado en el contexto de una empresa.
>
> dpikeo debe ser el primer tenant/empresa de la nueva arquitectura.
>
> La conexión del número oficial deberá realizarse mediante Meta WhatsApp Embedded Signup desde un dominio controlado por Siglo Tecnológico.
>
> Una vez completada esta implementación, incorporar un segundo cliente deberá requerir únicamente crear la empresa, conectar su WhatsApp y configurar su chatbot, sin modificar variables de entorno ni código fuente.
