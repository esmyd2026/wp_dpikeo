# Base de Conocimiento - DPIKEOS

Documento operativo para administrar, mantener y evolucionar el bot comercial
de WhatsApp de **DPIKEOS**.

**Proyecto:** `dpikeoChatbot`  
**Demo activa:** `dpikeos`  
**Actualizado:** 2026-08-13  
**Tecnología:** Laravel 12 / PHP 8.5 / WhatsApp Cloud API

---

## 1. Identidad de marca

| Elemento | Valor |
|---|---|
| Nombre comercial | DPIKEOS |
| Comunidad | Club Dpikeolovers |
| Instagram oficial | https://www.instagram.com/dpikeos_/ |
| Estilo | Cercano, ágil, apetitoso y profesional |
| Objetivo | Convertir clientes ocasionales en Dpikeolovers recurrentes |

### Principios de atención

- El cliente no debe buscar escribiendo: se guía con botones, listas y Flow.
- La compra debe requerir el menor número de decisiones posible.
- El bot no usa IA: entrega una experiencia determinista y administrable.
- Precios, productos, variaciones y extras se validan siempre desde la base de datos.
- Nunca prometer tiempos, cobertura, promociones o disponibilidad que no estén configurados.

---

## 2. Experiencia de compra objetivo

```text
Inicio
  -> Pedir ahora
  -> Categoría
  -> Producto
  -> Pedido rápido (WhatsApp Flow)
       -> Variación
       -> Extras
       -> Cantidad
       -> Entrega o retiro
       -> Dirección / referencia
       -> Pago
       -> Nota
  -> Pedido registrado
  -> Confirmación humana de disponibilidad y preparación
```

### Reglas UX

1. Usar máximo tres botones por pantalla.
2. Usar listas para categorías y productos; son más rápidas y escalan hasta 10 filas.
3. Abrir un WhatsApp Flow solo después de que el cliente elija un producto.
4. El Flow debe concentrar personalización, entrega y pago en una única experiencia.
5. Conservar catálogo y carrito tradicional como respaldo cuando el cliente no pueda abrir un Flow.

---

## 3. Catálogo DPIKEOS

El catálogo se administra desde **Panel admin -> Productos**. Cada producto
puede tener imagen, descripción, disponibilidad, cantidad, variaciones y extras.

### Operación completa: producto a cierre

```text
Producto activo con stock
  -> Menú WhatsApp o micrositio
  -> Carrito (variación, extras y nota)
  -> Pedido pendiente
  -> Cliente confirma y elige pago
  -> Confirmado / pago pendiente: se reserva inventario
  -> Comprobante (si aplica): se respalda de forma privada
  -> Pagado -> completado
  -> Cancelado: se libera la reserva de inventario
```

#### Estados de pedido

| Estado | Significado operativo | Inventario |
|---|---|---|
| `pending` | Pedido creado, esperando confirmación del cliente | No reservado |
| `payment_pending` | Cliente confirmó; falta validar pago o comprobante | Reservado |
| `confirmed` | Confirmado para preparar/entregar; efectivo contra entrega o pago validado | Reservado |
| `paid` | Pago validado | Reservado |
| `completed` | Entregado o cerrado | Reservado (venta final) |
| `cancelled` | Pedido cancelado | La reserva se libera automáticamente |

No se puede mover un pedido terminado o cancelado a otro estado desde el panel.
Esto evita cambiar ventas históricas por accidente.

#### Inventario

- **Panel -> Productos:** crea, edita precios, imágenes, variaciones, extras y stock.
- **Panel -> Inventario:** ve existencias, agotados, stock bajo y el historial de reservas, cancelaciones y ajustes.
- Los productos con stock `0` se ocultan del micrositio y del catálogo conversacional.
- La confirmación comprueba el stock dentro de una transacción: no vende más unidades de las disponibles.
- Si un producto ya tiene pedidos, al “eliminarlo” se desactiva para preservar PDFs, reportes e historial.

#### Pago y comprobantes

- El cliente siempre debe escoger **transferencia**, **efectivo** o **tarjeta** antes de confirmar.
- Transferencia y tarjeta solicitan comprobante por WhatsApp; el equipo debe revisar el archivo y cambiar el pedido a `paid` cuando corresponda.
- Cada comprobante nuevo se descarga a `storage/app/payment-proofs/` como respaldo privado. Incluya esa carpeta en las copias de seguridad.
- El PDF del pedido **no sustituye una factura electrónica**. La factura se gestiona desde el detalle del pedido y debe emitirse en el sistema contable autorizado.

#### Reportes y cierres diarios

1. En **Pedidos**, filtrar pendientes, pagos pendientes y comprobantes recibidos.
2. Validar comprobantes, actualizar el estado y agregar feedback interno.
3. En **Inventario**, reponer/ajustar stock físico y revisar diferencias.
4. En **Reportes pedidos**, revisar totales, estados y tendencia diaria; exportar XLSX para contabilidad.
5. Marcar como `completed` solamente los pedidos efectivamente entregados/retirados.

#### Respaldo mínimo

- Diario: base de datos MySQL y `storage/app/payment-proofs/`.
- Antes de desplegar: base de datos, `.env` cifrado/guardado de forma segura y `storage/`.
- Semanal: probar una restauración en una base separada; un backup no probado no es un respaldo confiable.

### Categorías activas

| Categoría | Productos |
|---|---:|
| Boxes | 3 |
| Combos Familiares | 2 |
| Combos Premium | 1 |
| Combos con Arroz | 2 |
| Hamburguesas | 4 |
| Salchipapas y Especiales | 6 |
| Salsas | 1 |

### Productos y precios base

| SKU | Producto | Precio desde |
|---|---|---:|
| DP001 | Ultra Box | $5.99 |
| DP002 | Box Tender | $4.50 |
| DP003 | Deditos de Pollo | $4.50 |
| DP004 | Combo Familiar | $10.99 |
| DP005 | Mega Familiar | $17.99 |
| DP006 | Mega D-Box | $7.99 |
| DP007 | Rice and Broaster | $4.60 |
| DP008 | Combo 1 | $5.60 |
| DP009 | Mega Po | $4.99 |
| DP010 | Total Chicken | $5.15 |
| DP011 | Tocino Chicken | $4.50 |
| DP012 | Imperdible de Pollo | $4.50 |
| DP013 | Bacon Cheese | $3.75 |
| DP014 | Salchi Papas | $2.75 |
| DP015 | Salchi Pollo | $4.25 |
| DP016 | Mega Remix | $4.80 |
| DP017 | Chili Cheese Res + Irresistible | $4.50 |
| DP018 | Deditos Cheese | $6.50 |
| DP019 | Salsa Spice Chicken | $0.50 |

### Variaciones configuradas

| Producto | Opciones |
|---|---|
| Ultra Box | Con gaseosa $5.99 / Sin gaseosa $5.40 |
| Box Tender | Con gaseosa $4.50 / Sin gaseosa $3.75 |
| Deditos de Pollo | Con gaseosa $4.50 / Sin gaseosa $3.75 |
| Combo Familiar | Completo $10.99 / Solo presas $7.50 |
| Mega Familiar | Completo $17.99 / Solo presas $14.99 |
| Rice and Broaster | Con gaseosa $4.60 / Sin gaseosa $3.99 |
| Combo 1 | Con gaseosa $5.60 / Sin gaseosa $4.99 |
| Mega Po | Completa $4.99 / Sin gaseosa $3.50 |
| Total Chicken | Completa $5.15 / Sin gaseosa $4.50 |
| Tocino Chicken | Completa $4.50 / Sin gaseosa $3.99 |
| Chili Cheese Res + Irresistible | Con gaseosa $4.50 / Sin gaseosa $3.75 |
| Salsa Spice Chicken | Vaso pequeño $0.50 / Vaso grande $2.99 |

### Extras sugeridos

- Salsa Spice Chicken pequeña y grande
- Papas extra
- Queso extra
- Tocino extra
- Gaseosa adicional

Los extras y sus importes se editan en cada producto. Una línea por opción con
formato `Nombre | Precio`, por ejemplo: `Papas extra | 1.00`.

---

## 4. Administración web

| Qué se administra | Panel / módulo |
|---|---|
| Productos, imágenes, precio y stock | Productos |
| Categorías del menú | Categorías / Menús |
| Variaciones y extras | Productos -> editar producto |
| Mensajes, botones y listas | Flujo del bot |
| Flow ID de Meta | Flujo del bot -> Catálogo de productos |
| Identidad, saludo y colores | Configuración chatbot |
| Perfil comercial y datos de contacto | Perfil WhatsApp Business |
| Pedidos y estados | Pedidos / Chats |
| Demo activa | Configuración -> Demo de catálogo |

### Campos de producto

- **Variaciones:** una por línea, `Nombre | Precio`.
- **Extras:** una por línea, `Nombre | Precio`; el precio puede omitirse si se
  confirma manualmente.
- **Stock:** desactivar el producto si no debe aparecer en WhatsApp.
- **Imagen:** se muestra como cabecera al abrir el detalle en WhatsApp.

---

## 5. Flujo conversacional actual

### Menú principal

- `Pedir ahora`
- `Mi pedido`
- `Club y ayuda`

### Palabras de respaldo

| Cliente escribe | Resultado |
|---|---|
| hola, buenas, inicio, menú | Menú principal |
| productos, catálogo, precios | Catálogo |
| asesor, agente, humano | Derivación a equipo humano |
| SKU, por ejemplo DP001 | Detalle de producto |
| contacto | Datos configurados de la empresa |
| redes | Instagram oficial |

### IA

`chatgpt_enabled` está desactivado para DPIKEOS. El bot usa reglas, datos de
catálogo y acciones interactivas; no genera respuestas de IA.

---

## 6. WhatsApp Flow nativo

El proyecto está preparado para enviar y recibir un **WhatsApp Flow de Meta**.
El Flow debe publicarse en WhatsApp Manager y su ID debe pegarse en el panel.

### Cómo activarlo

1. Crear y publicar el Flow en Meta.
2. Abrir **Flujo del bot -> Catálogo de productos**.
3. Pegar el identificador en **Flow ID de pedido rápido**.
4. Guardar los cambios.
5. Al abrir un producto, el botón cambiará a **Pedido rápido**.

### Datos de entrada del Flow

La aplicación envía:

```json
{
  "product_sku": "DP001",
  "product_name": "Ultra Box",
  "base_price": "5.99"
}
```

### Datos de salida requeridos

```json
{
  "product_sku": "DP001",
  "quantity": 2,
  "variation": "Sin gaseosa",
  "extras": ["Salsa Spice Chicken pequeña"],
  "fulfilment": "delivery",
  "address": "Dirección de entrega",
  "payment_method": "efectivo",
  "customer_note": "Timbre azul"
}
```

El webhook recibe `nfm_reply`, vuelve a consultar precios y opciones del
catálogo local, crea el carrito/pedido y devuelve un número de orden. No se
acepta un valor monetario calculado por el cliente.

La especificación detallada está en
[WHATSAPP-FLOW-DPIKEOS.md](WHATSAPP-FLOW-DPIKEOS.md).

---

## 7. Pedidos y operación

### Estados

- Pendiente
- Confirmado
- En preparación
- Listo para entregar
- Entregado / completado
- Cancelado

### Flujo por áreas: caja, cocina y despacho

El pedido se opera como una comanda, no como un chat aislado:

1. **Caja:** recibe el pedido en estado `Pendiente`, valida pago y disponibilidad
   y lo cambia a `Confirmado` (o `Pagado` cuando aplique). En este punto se
   reserva el inventario.
2. **Cocina:** el tablero de **Comandas** muestra los pedidos confirmados en
   `En cola`. El equipo pulsa **Iniciar preparación** y el pedido pasa a
   `En preparación`.
3. **Cocina:** al terminar, pulsa **Marcar listo**. El pedido pasa a
   `Listo para entregar`.
4. **Despacho / mostrador:** recibe los pedidos listos y pulsa **Entregado**.
   El estado final interno es `completed`.

No se permite saltar de un pedido nuevo a listo o entregado. Cada cambio queda
en `metadata.operational_timeline` con estado anterior, estado nuevo, fecha y
usuario responsable.

### Pantallas operativas

- `/admin/comandas`: tablero de trabajo con tres columnas: **En cola**, **En
  preparación** y **Listos para entregar**. Se actualiza automáticamente cada
  10 segundos.
- `/admin/comandas/pantalla`: vista de pantalla completa para un televisor. No
  muestra información personal del cliente; solo número de pedido, tiempo y
  productos. Requiere una sesión con permiso de pedidos.

Estas vistas usan los permisos existentes `orders.view` y `orders.update`. Para
un usuario de cocina se recomienda crear un rol con únicamente esos permisos.

### Confirmación obligatoria del equipo

El pedido recibido desde el Flow queda pendiente. Antes de confirmar al cliente,
el operador debe validar:

1. Disponibilidad de producto y extras.
2. Precio final.
3. Dirección o modalidad de retiro.
4. Método de pago y comprobante, cuando aplique.
5. Tiempo real de preparación y entrega.

---

## 8. Fidelización (siguiente fase)

Regla propuesta: **$1 = 1 punto**.

| Puntos | Beneficio |
|---:|---|
| 50 | Bebida gratis |
| 100 | Papas gratis |
| 150 | Hamburguesa gratis |
| 300 | Combo gratis |

Funcionalidades planificadas:

- Recompra de último pedido.
- Promociones por horario, producto, primer pedido y cumpleaños.
- Cupones y referidos.
- Pedidos programados.
- Seguimiento de repartidor.
- Encuesta automática posterior a la entrega.

No deben comunicarse como disponibles hasta que estén implementadas y activadas
en el panel.

---

## 9. Archivos técnicos relevantes

| Archivo | Responsabilidad |
|---|---|
| `database/seeders/DpikeosDemoSeeder.php` | Activa y configura la demo DPIKEOS |
| `database/seeders/data/dpikeos_catalog.php` | Categorías, productos, precios y variaciones iniciales |
| `database/seeders/data/dpikeos_flow.php` | Mensajes y navegación inicial |
| `app/Services/WhatsappService.php` | Webhook, catálogo, carrito y respuesta `nfm_reply` |
| `app/Services/MarketingCatalogBuilder.php` | Listas dinámicas de categorías y productos |
| `app/Services/MarketingFlowPayloadBuilder.php` | Envío de botones, listas y Flows |
| `app/Http/Controllers/Admin/MarketingFlowController.php` | Configuración web del Flow |
| `resources/views/admin/marketing-flow/edit.blade.php` | Campos del panel de flujo |
| `docs/WHATSAPP-FLOW-DPIKEOS.md` | Contrato técnico del Flow nativo |

### Comandos útiles

```bash
# Aplicar la demo DPIKEOS en una base nueva o reiniciada
php artisan db:seed --class=DpikeosDemoSeeder --force

# Revisar migraciones
php artisan migrate:status

# Limpiar y reconstruir configuración
php artisan config:clear
php artisan config:cache
```

---

## 10. Estado actual

Implementado:

- Demo DPIKEOS activa.
- Catálogo base con 19 productos y variantes principales.
- IA desactivada.
- Navegación por botones y listas.
- Carrito y checkout existentes.
- Campos administrables de variaciones y extras.
- Integración para apertura de WhatsApp Flow por producto.
- Recepción de `nfm_reply` y creación de pedido desde Flow.

Pendiente de configuración externa:

- Crear y publicar el Flow en Meta.
- Pegar su Flow ID en el panel.
- Configurar dirección, cobertura, horarios y métodos de pago reales.
- Cargar imágenes finales de productos.
- Definir precios finales para cada extra.

---

## 11. Operación exclusiva DPIKEOS y sucursales

El proyecto quedó depurado para operar únicamente DPIKEOS:

- Catálogo operativo: 7 categorías y 19 productos DPIKEOS.
- Se retiraron productos, categorías, pedidos demo, respuestas y menús heredados de otras marcas.
- La pantalla de acceso y el inicio administrativo comunican operación DPIKEOS, no la plataforma genérica.

### Sucursales

Cada empresa dispone de una sucursal **Matriz** inicial. Desde **Panel administrativo → Sucursales** se pueden crear y actualizar locales adicionales, indicando nombre, código, teléfono, dirección, estado y cuál será la predeterminada.

Al crear un pedido desde caja aparece el selector de sucursal cuando hay más de una activa. La sucursal se guarda en el pedido y aparece en la comanda térmica. Los pedidos recibidos por WhatsApp se asignan automáticamente a la sucursal predeterminada.

### Franquicias y catálogo

Las franquicias son la unidad comercial del catálogo; las sucursales son los locales físicos que atienden los pedidos. Desde **Panel administrativo → Franquicias** se administra cada franquicia con nombre, identificador, descripción y estado.

- Cada categoría pertenece a una franquicia.
- Cada producto pertenece a una franquicia y debe coincidir con la de su categoría.
- DPIKEOS · Club Dpikeolovers es la franquicia inicial y predeterminada.
- El filtro de Productos y Categorías usa franquicias, no las antiguas “empresas demo”.
