# Flujos de pedido por el bot de WhatsApp — instructivo

Este documento describe, paso a paso, qué le dice el bot al cliente en cada
escenario de pedido conversacional (catálogo → carrito → checkout), con el
texto exacto (o su plantilla) que se envía en cada iteración. No cubre el
"Pedido rápido con WhatsApp Flow" (formulario nativo de Meta, ver
`WHATSAPP-FLOW-DPIKEOS.md`) ni el micrositio de pedido múltiple — es
específicamente el flujo conversacional dentro del chat.

> Los textos marcados como "mensaje editable" se pueden cambiar desde el
> panel (**Flujo del bot → Pasos del checkout**, o **Configuración del bot →
> Mensajes automáticos**) sin tocar código. Si no se configuran, el bot usa
> el texto por defecto que se muestra aquí.

## Índice

1. [Pasos comunes a todo pedido](#1-pasos-comunes-a-todo-pedido)
2. [Flujo: Para Servir (comer en el local)](#2-flujo-para-servir-comer-en-el-local)
3. [Flujo: Para Llevar — Retiro en local](#3-flujo-para-llevar--retiro-en-local)
4. [Flujo: Para Llevar — Delivery a domicilio](#4-flujo-para-llevar--delivery-a-domicilio)
5. [Sub-flujo de pago: Efectivo](#5-sub-flujo-de-pago-efectivo)
6. [Sub-flujo de pago: Transferencia / Depósito](#6-sub-flujo-de-pago-transferencia--depósito)
7. [Sub-flujo de pago: Tarjeta](#7-sub-flujo-de-pago-tarjeta)
8. [Confirmación de costo adicional (envío / para llevar)](#8-confirmación-de-costo-adicional-envío--para-llevar)
9. [Envío y verificación del comprobante de pago](#9-envío-y-verificación-del-comprobante-de-pago)
10. [Seguimiento del pedido — "Mis pedidos"](#10-seguimiento-del-pedido--mis-pedidos)
11. [Cambios de estado posteriores](#11-cambios-de-estado-posteriores-cocina-listo-entregado)
12. [Cancelación del pedido](#12-cancelación-del-pedido)

---

## 1. Pasos comunes a todo pedido

Todo pedido pasa por la misma máquina de pasos (`finalizarCompra`), en este
orden. Cada paso se salta automáticamente si ya tiene el dato guardado, o si
el admin lo desactivó desde el editor visual (en ese caso usa un valor por
defecto configurado).

```
Carrito con productos
   │
   ▼
Paso 1 — Sucursal
   │
   ▼
Paso 2 — ¿Para servir o para llevar?
   │
   ├─ "Para servir" ──────────────────────► ver Flujo 2 (sin pago, sin dirección)
   │
   └─ "Para llevar"
         │
         ▼
      Paso 2.5 — Método de pago (transferencia / efectivo / tarjeta)
         │
         ├─ "Tarjeta" ──────────────────────► link externo, el bot no pregunta nada más
         │                                    (ver Flujo 7)
         │
         └─ "Transferencia" o "Efectivo"
               │
               ▼
            Paso 3 — ¿Retiro en local o delivery?
               │
               ├─ "Retiro en local" ─────────► ver Flujo 3
               │
               └─ "Delivery"
                     │
                     ▼
                  Paso 4 — Dirección + nombre de quien recibe ──► ver Flujo 4
```

El método de pago se pregunta apenas se sabe que el pedido es "para llevar"
(antes de retiro/delivery, dirección y nota) porque la respuesta determina
el resto del flujo: si es tarjeta, el bot manda un link externo y no sigue
preguntando nada más para ese pedido; si es transferencia o efectivo, sigue
exactamente el flujo normal.

Después de retiro/delivery (y dirección, si aplica), siempre sigue:

- **Nota opcional** del pedido.
- **Resumen final** con botones "✅ Confirmar pedido" / "❌ Cancelar pedido".

### Paso 1 — Sucursal

- Si el cliente **ya pidió antes** desde una sucursal:

  > 📍 *Sucursal de tu pedido*
  > ¿Pedirás nuevamente desde *{sucursal}*?
  >
  > `[✅ Sí, la misma]` `[🔄 Elegir otra]`

- Si es la **primera vez**, o eligió "Elegir otra" (lista desplegable):

  > 📍 *¿Desde qué sucursal pedirás?*
  >
  > (lista con el nombre de cada sucursal activa)

- Si el negocio solo tiene una sucursal (o ninguna configurada), este paso
  se salta solo.

### Paso 2 — Para servir / Para llevar

Se salta automáticamente (queda fijo en "Para llevar") si la sucursal tiene
el servicio en mesa desactivado, o si el admin apagó este paso.

> 🍽️ *¿Tu pedido es para llevar o para servir?*
>
> `[🥡 Para llevar]` `[🍽️ Para servir]`

### Paso 3 — Retiro en local / Delivery (solo si es "para llevar")

> 🚗 *¿Retiras en el local o prefieres delivery?*
> 🛵 El delivery tiene un costo adicional que te confirmaremos por este chat.
>
> `[🏬 Retiro en local]` `[🛵 Delivery]`

### Nota del pedido (todos los flujos)

> 🎉 ¡Ya casi terminamos con tu pedido!
> Solo falta la nota (opcional) *y confirmamos tu pedido* ← si es "para servir"
> Solo falta la nota (opcional) *y el pago* ← cualquier otro caso
>
> Si quieres, escribe una nota (instrucciones especiales, preferencias o
> cualquier detalle importante), o toca el botón para continuar sin nota.
>
> `[✅ Sin nota]`

El cliente puede responder con texto libre (se guarda como nota) o tocar el
botón para omitirla.

**Escape valve:** en cualquier punto de estos pasos, si el cliente escribe
`cancelar` o `salir`, el pedido se cancela (ver [sección 12](#12-cancelación-del-pedido)).

---

## 2. Flujo: Para Servir (comer en el local)

No pide método de pago ni dirección — se paga en caja al llegar.

```
Sucursal → "Para servir" → Nota → ¡Listo! (sin pago, sin resumen a confirmar)
```

Al terminar la nota, el bot genera el número de pedido directo (no hay paso
de "confirmar pedido"; el carrito pasa a estado *pending*):

> ✅ *¡Pedido registrado!*
> Pedido *#{orderNumber}*
>
> 🚚 *Entrega*
> Sucursal: {sucursal}
> Tipo: Para servir
>
> 💰 *Total:* $XX.XX
>
> 🧾 Pasa a caja con tu número de pedido para cancelar. ¡Gracias por tu pedido!
>
> `[📦 Mis pedidos]` `[🏠 Menú principal]`

No hay paso de comprobante de pago en este flujo: el pago se hace físicamente
en caja.

---

## 3. Flujo: Para Llevar — Retiro en local

Aplica solo si el método de pago elegido fue transferencia o efectivo — con
tarjeta el pedido termina antes, en el link externo (ver [sección 7](#7-sub-flujo-de-pago-tarjeta)).

```
Sucursal → "Para llevar" → Método de pago (transf./efectivo) → "Retiro en local"
  → Nota → Resumen → Confirmar → (según método) comprobante o confirmación directa
```

No pide dirección ni nombre de quien recibe (el mismo cliente retira). El
resumen que ve antes de confirmar incluye:

> 🚚 *Entrega*
> Sucursal: {sucursal}
> Tipo: Para llevar
> Entrega: Retiro en el local

Si el negocio cobra un costo por el empaque/"para llevar", aparece en el
desglose como **pendiente** hasta que un vendedor lo confirme desde el panel
(ver [sección 8](#8-confirmación-de-costo-adicional-envío--para-llevar)):

> 💰 *Total productos:* $XX.XX
> Costo para llevar: por confirmar

Sigue con la [nota y el resumen](#1-pasos-comunes-a-todo-pedido) según el
método de pago ya elegido — [efectivo](#5-sub-flujo-de-pago-efectivo) o
[transferencia](#6-sub-flujo-de-pago-transferencia--depósito).

---

## 4. Flujo: Para Llevar — Delivery a domicilio

Aplica solo si el método de pago elegido fue transferencia o efectivo — con
tarjeta el pedido termina antes, en el link externo (ver [sección 7](#7-sub-flujo-de-pago-tarjeta)).

```
Sucursal → "Para llevar" → Método de pago (transf./efectivo) → "Delivery"
  → Dirección (texto libre)
  → ¿A nombre de quién recibimos? (botón con tu nombre / "Otro nombre")
  → Nota → Resumen → Confirmar
  → Costo de envío pendiente de confirmación por un vendedor
  → (según método) comprobante o confirmación directa
```

### Paso: dirección de entrega

> 📍 Escríbenos la *dirección completa* de entrega (calle, sector, referencia).

El cliente responde con texto libre. También se acepta si comparte su
**ubicación GPS** de WhatsApp en vez de escribir — el bot la guarda con un
link de Google Maps.

### Paso: nombre de quien recibe

> 🧑 ¿A nombre de quién recibimos el pedido?
>
> `[{nombre del contacto, ej. "Gregorio Osorio"}]` `[✍️ Otro nombre]`

- Si toca el botón con su propio nombre → se usa ese nombre y continúa.
- Si toca "Otro nombre" → el bot espera que escriba el nombre por texto
  libre y lo usa como destinatario.

En este momento el bot calcula un **estimado referencial** del costo de
envío (tarifa mínima configurada) pero **no lo suma al total todavía**: el
monto real de envío lo confirma un vendedor manualmente desde el panel de
Pedidos, según la dirección real.

### Resumen antes de confirmar

> 🚚 *Entrega*
> Sucursal: {sucursal}
> Tipo: Para llevar
> Dirección: {dirección}
> Recibe: {nombre}
>
> 💰 *Total productos:* $XX.XX
> Envío: por confirmar

Sigue con la [nota y el resumen](#1-pasos-comunes-a-todo-pedido) según el
método de pago ya elegido. Una vez el vendedor confirma el costo de envío, el bot le manda al cliente
el mensaje de [costo adicional confirmado](#8-confirmación-de-costo-adicional-envío--para-llevar)
con el total final (y, si paga por transferencia, los datos bancarios).

---

## 5. Sub-flujo de pago: Efectivo

Aplica para "Para llevar" (retiro o delivery). No pide comprobante — se paga
al recibir o retirar.

1. El cliente elige **💵 Pago en efectivo** de la lista de métodos de pago.
2. El bot arma el resumen completo:

   > 📋 *Resumen de tu pedido*
   > Pedido *#{orderNumber}*
   >
   > {lista de productos, cantidad, precio unitario y subtotal}
   >
   > 🚚 *Entrega*
   > (según el flujo — sucursal / tipo / dirección / recibe)
   >
   > 💳 *Pago*
   > Pago en efectivo
   >
   > {desglose de costos}
   >
   > 📝 *Nota:* {nota, si escribió una}
   >
   > ¿Confirmas tu pedido?
   >
   > `[✅ Confirmar pedido]` `[❌ Cancelar pedido]`

3. Al tocar **Confirmar pedido**:
   - Si todavía falta que un vendedor confirme el costo de envío/para
     llevar, el pedido queda registrado y el bot avisa que confirmará el
     total pronto (no pide nada más por ahora).
   - Si el total ya es final, el pedido pasa a *confirmado*:

     > ✅ *¡Pedido confirmado!*
     > 📦 *Número de pedido:* {orderNumber}
     > {desglose de costos}
     > 💳 *Método de pago:* Pago en efectivo
     >
     > {bloque de entrega}
     > Te contactaremos pronto para coordinar los siguientes pasos.
     >
     > `[📦 Mis pedidos]` `[🏠 Menú principal]`

No hay paso de comprobante: el efectivo no lo requiere.

---

## 6. Sub-flujo de pago: Transferencia / Depósito

Igual estructura que efectivo, pero **sí exige comprobante** antes de que el
pedido quede confirmado, y desde el panel se le puede agregar al cliente los
datos de la cuenta a la que debe transferir.

1. El cliente elige **🏦 Transferencia** de la lista de métodos de pago.
2. Mismo resumen que en efectivo, pero con:

   > 💳 *Pago*
   > Transferencia o depósito bancario

3. Al tocar **Confirmar pedido**:
   - Si el total todavía no es final (falta confirmar envío/para llevar):

     > 🕐 Tu pedido se encuentra registrado. Pronto nuestro equipo te
     > confirmará el total a pagar y ahí te pediremos tu comprobante.

     (Aquí el bot **no** pide comprobante todavía — esperaría a que el total
     cambie y le estaría pidiendo pagar un monto equivocado.)

   - Si el total ya es final, el pedido pasa a *pago pendiente* y el bot pide
     el comprobante en el mismo mensaje:

     > ✅ *¡Pedido confirmado!*
     > 📦 *Número de pedido:* {orderNumber}
     > {desglose de costos}
     > 💳 *Método de pago:* Transferencia o depósito bancario
     > {bloque de entrega}
     >
     > 🕐 Tu pedido queda *pendiente de verificación* hasta que recibamos tu
     > comprobante. En cuanto lo enviemos a revisión, te confirmamos por
     > este mismo chat.
     >
     > 📎 *Envío de Comprobante*
     > Pedido *{orderNumber}*
     > Envía una imagen o PDF de tu comprobante de pago.
     > *(mensaje editable desde el panel)*

4. El cliente manda una foto o PDF → ver [sección 9](#9-envío-y-verificación-del-comprobante-de-pago).

Si el pedido llevaba envío o costo para llevar pendiente de confirmar, la
petición del comprobante llega recién en el mensaje de **costo adicional
confirmado** (sección 8), no antes — y ese mismo mensaje ahora incluye los
datos bancarios configurados en el panel (banco, número de cuenta, titular,
Zelle, Pago Móvil, etc., según lo que se haya llenado en **Configuración del
bot → Datos para transferencias o depósitos**).

---

## 7. Sub-flujo de pago: Tarjeta

A diferencia de transferencia y efectivo, el pago con tarjeta **no se
procesa en este chat** — el negocio tiene su propia página web externa con
su propio sistema de cobro. El bot solo manda un link; no pide comprobante,
no arma resumen, no pide sucursal-retiro/delivery ni dirección ni nota.

1. El cliente elige **💳 Pago con tarjeta** de la lista de métodos de pago
   (que aparece justo después de "Para llevar", antes de cualquier otra
   pregunta).
2. El bot responde de inmediato con un botón que lleva a la página externa:

   > {mensaje configurado en el panel, ej. "💳 Puedes pagar con tarjeta
   > directamente aquí:"}
   >
   > `[Pagar en línea]` → abre el link configurado

3. Ahí termina la conversación de este pedido en el bot. Si el cliente
   escribe algo más después, el bot solo le recuerda el link — no vuelve a
   preguntar nada del pedido.

> El mensaje y el link se configuran desde **Configuración del bot →
> Credenciales de WhatsApp / Link de pago con tarjeta**. Si no se configura
> ningún link, la opción "Pago con tarjeta" no aparece en la lista de
> métodos de pago.

> Como este proyecto no maneja el pago, no se genera comprobante ni se
> actualiza el estado del pedido más allá de dejar guardado que el cliente
> eligió "tarjeta" — el seguimiento del pago real ocurre en la página
> externa.

---

## 8. Confirmación de costo adicional (envío / para llevar)

Este mensaje lo dispara un **vendedor desde el panel** (módulo de Pedidos),
no el propio bot automáticamente — es el paso manual donde el negocio revisa
la dirección real (para calcular el envío) o confirma el costo del empaque
para llevar.

> 💰 *Costo adicional confirmado*
>
> Dirección: {dirección} ← solo si es delivery
> Recibe: {nombre} ← solo si es delivery
> Costo de envío: $X.XX ← solo si aplica envío
> Costo para llevar: $X.XX ← solo si aplica "para llevar"
> Total final del pedido: $XX.XX
>
> 🏦 *Datos para tu transferencia o depósito*
> {texto configurado en el panel} ← **solo si el método de pago es transferencia**

Si el pedido necesitaba comprobante de pago y todavía no se le había pedido
(porque el total no era final hasta ahora), el bot agrega automáticamente,
en el mismo mensaje, la petición de comprobante (sección 9).

---

## 9. Envío y verificación del comprobante de pago

Aplica a Transferencia y Tarjeta.

1. El bot ya pidió el comprobante (secciones 6, 7 u 8). Mientras el pedido
   está en este estado, el bot **no** responde con el menú normal ni IA: solo
   acepta la imagen/PDF del comprobante o la palabra "cancelar".
2. El cliente envía una **foto o PDF**. El bot lo asocia al pedido y
   responde:

   > ✅ Comprobante recibido para el pedido *{orderNumber}*. Lo verificaremos
   > pronto.
   > *(mensaje editable desde el panel)*
   >
   > `[📦 Mis pedidos]` `[🏠 Menú principal]`

3. El comprobante queda visible en el panel, dentro del detalle del pedido
   ("Comprobante de pago del cliente"), con foto/PDF, método y fecha de
   recepción. Ahí el vendedor tiene un botón **"Confirmar pago recibido"**
   que marca el pedido como pagado.
4. Al confirmarse el pago desde el panel, el bot le avisa al cliente (ver
   sección 11):

   > 📦 Tu pedido *{orderNumber}* cambió de estado:
   > *Pagado ✅*
   > *(mensaje editable desde el panel)*

---

## 10. Seguimiento del pedido — "Mis pedidos"

El cliente puede pedir el menú de "Mis pedidos" en cualquier momento. Solo
muestra pedidos **en curso** (ya entregados/cancelados no aparecen ahí — el
cliente no necesita que se lo sigan recordando).

Si no tiene pedidos activos:

> 📦 *Historial de Pedidos*
> No tienes pedidos realizados aún.
> ¿Te gustaría ver nuestros productos?
>
> `[🛍️ Ver productos]` `[🏠 Menú principal]`

Si tiene pedidos, se agrupan en hasta tres bloques (solo aparecen los que
apliquen):

> 📦 *Tus Pedidos*
>
> ⏳ *Pedidos Pendientes de Confirmación*
> 🛒 *#{orderNumber}*
> 📅 Fecha: dd/mm/aaaa hh:mm
> 💰 Total: $XX.XX
> 💳 Método de pago: {método}
> 📋 Items: • Producto x2 …
> 📝 Nota: {nota}
>
> ✅ *Pedidos en Proceso*   ← confirmado / en preparación / listo
> (mismo formato)
>
> 💳 *Pedidos Pendientes de Pago*
> (mismo formato) +
> 📎 *Estado:* Pendiente de comprobante  ← si aún no lo envió
> ✅ *Estado:* Comprobante en revisión   ← si ya lo envió y falta que lo verifiquen

---

## 11. Cambios de estado posteriores (cocina, listo, entregado)

A partir de "confirmado", el pedido avanza por estados que el negocio va
cambiando desde el panel (comanda / Pedidos): **Confirmado → En preparación
→ Listo → Entregado**, o directo a **Pagado** cuando se confirma el
comprobante. Cada cambio hecho por el staff (no por el propio cliente) le
manda automáticamente al cliente:

> 📦 Tu pedido *{orderNumber}* cambió de estado:
> *{etiqueta}*
> *(mensaje editable desde el panel)*

Etiquetas por estado:

| Estado interno | Texto que ve el cliente |
|---|---|
| `confirmed` | Confirmado ✅ |
| `payment_pending` | Pago pendiente |
| `paid` | Pagado ✅ |
| `preparing` | En preparación 👨‍🍳 |
| `ready` | Listo 🎉 |
| `completed` | Entregado ✅ |
| `cancelled` | Cancelado ❌ |

Este aviso **no se manda** si: el número del cliente es sintético (pedido
creado desde el punto de venta, sin WhatsApp real), o si pasaron más de 24 h
desde su último mensaje (la ventana de conversación de WhatsApp ya se
cerró — Meta no permite mandar texto libre fuera de esa ventana).

---

## 12. Cancelación del pedido

El cliente puede cancelar tocando el botón "❌ Cancelar pedido" del resumen,
o escribiendo `cancelar`/`salir` mientras tiene un paso del checkout
pendiente (sucursal, tipo de pedido, delivery, nota o método de pago sin
resolver).

> ❌ *Pedido cancelado*
> Tu pedido ha sido cancelado.
> ¿Qué deseas hacer?
>
> `[🛍️ Ver productos]` `[🏠 Menú principal]`

Si el pedido ya tenía stock reservado (por haber llegado a un estado
confirmado), la cancelación libera ese inventario automáticamente.
