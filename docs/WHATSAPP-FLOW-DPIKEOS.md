# Pedido rápido DPIKEOS con WhatsApp Flows

El catálogo continúa usando listas de WhatsApp para que el cliente encuentre el
producto sin escribir. Al abrir el detalle de un producto, el botón **Pedido
rápido** abre un Flow nativo de Meta que reúne personalización, cantidad,
entrega/retiro, dirección, pago y nota en una sola experiencia.

## Publicación en Meta

1. En WhatsApp Manager cree y publique un Flow de tipo formulario.
2. Defina una pantalla inicial llamada `ORDER`.
3. Permita recibir los valores de inicio `product_sku`, `product_name` y
   `base_price` enviados por la aplicación.
4. Al completar el Flow, envíe su resultado mediante `Complete`.
5. En el panel de esta aplicación abra **Flujo del bot → Catálogo de
   productos → Pedido rápido con WhatsApp Flow**, pegue el ID publicado y guarde.

### Cifrado requerido por Meta

Antes de publicar, Meta solicita endpoint URI, número de teléfono, clave pública
y aplicación Meta. En este proyecto:

```bash
# Genera las claves solo una vez
php artisan whatsapp:flow-keys

# Registra la clave pública en el número Cloud API configurado en .env
php artisan whatsapp:flow-register-public-key
```

La segunda orden usa `WHATSAPP_TOKEN` y `WHATSAPP_PHONE_NUMBER_ID` del `.env`.
No muestra ni envía la clave privada. Para pruebas, el Endpoint URI de Meta debe
ser `https://TU-URL-NGROK/api/whatsapp/flows/endpoint`.

## Campos del resultado

Los nombres deben mantenerse para que el pedido se registre automáticamente:

| Campo | Requerido | Uso |
|---|---:|---|
| `product_sku` | sí | SKU del producto, por ejemplo `DP001` |
| `quantity` | sí | Cantidad entre 1 y 8 |
| `variation` | no | Debe coincidir con una variación configurada en el producto |
| `extras` | no | Lista de nombres de extras configurados en el producto |
| `fulfilment` | sí | `delivery` o `pickup` |
| `address` | para delivery | Dirección de entrega |
| `payment_method` | sí | Método elegido por el cliente |
| `customer_note` | no | Indicaciones para cocina o entrega |

Ejemplo de salida:

```json
{
  "product_sku": "DP001",
  "quantity": 2,
  "variation": "Sin gaseosa",
  "extras": ["Salsa Spice Chicken pequeña"],
  "fulfilment": "delivery",
  "address": "Cdla. ...",
  "payment_method": "efectivo",
  "customer_note": "Timbre azul"
}
```

La aplicación valida SKU, variación, extras y precios contra el catálogo local.
No acepta importes enviados desde WhatsApp. Cuando el cliente termina, crea el
pedido y lo deja pendiente de confirmación del equipo DPIKEOS.
# Decisión de experiencia de compra (agosto de 2026)

El Flow `Pedido rápido DPIKEOS` se publicó y su endpoint cifrado está activo.
Sin embargo, no debe usarse como catálogo ni como personalizador de un producto
individual. Los datos iniciales enviados con `flow_action = navigate` pueden
aparecer literalmente en algunos clientes de WhatsApp; por esa razón no se
debe depender de `${data.product_name}` ni `${data.base_price}` para mostrar
el producto elegido.

## Experiencia recomendada para DPIKEOS

La experiencia profesional se divide en dos momentos:

1. **Descubrir y armar el pedido — Catálogo nativo de Meta.**
   - El bot envía un mensaje de catálogo o una lista de productos destacados.
   - El cliente ve foto, nombre, descripción y precio directamente en WhatsApp.
   - Puede agregar varios productos a su carrito nativo y enviarlo en una sola
     acción. Los SKU son internos y nunca se muestran al cliente.
2. **Entrega y pago — Flow breve de checkout.**
   - Al recibir el carrito, la plataforma lo crea y muestra el resumen.
   - El cliente elige `Delivery` o `Retiro`.
   - Se abre un Flow corto correspondiente: dirección + referencia + pago para
     delivery, o solamente pago + indicaciones para retiro.
   - El equipo confirma disponibilidad y tiempo de preparación.

Esto evita abrir un formulario largo por cada producto y hace posible una
compra de varios ítems con fotos, como corresponde a un negocio de comida.

## Implementación en la plataforma

La plataforma ya reconoce el catálogo configurado mediante estas variables:

```env
WHATSAPP_CATALOG_ID=159080022030
WHATSAPP_CATALOG_THUMBNAIL_RETAILER_ID=DP001
WHATSAPP_CATALOG_ENABLED=true
```

Al pulsar `Ver menú`, el bot envía un mensaje de catálogo nativo de Meta. Al
enviar el carrito nativo, el webhook lo convierte en un pedido pendiente dentro
del panel, recalculando precios a partir del catálogo local para no confiar en
valores recibidos desde WhatsApp.

El Flow publicado de personalización por producto queda fuera del recorrido
principal. Se reutilizará o sustituirá por un Flow corto de checkout después de
que el carrito visual sea confirmado en pruebas reales.

## Requisitos pendientes

- Crear o conectar el catálogo de Commerce Manager al número de WhatsApp.
- Cargar fotos reales y atractivas de cada producto. Actualmente los productos
  locales no tienen imágenes asociadas, por lo que el sistema no puede mostrar
  una experiencia visual todavía.
- Vincular cada producto local con su `product_retailer_id` de Meta (ese valor
  puede ser el SKU interno, pero no se mostrará al cliente).
- Implementar en la plataforma el envío de catálogo/listas de productos Meta y
  el procesamiento del webhook `order` que retorna el carrito nativo.
- Crear un nuevo Flow exclusivo para checkout; no reutilizar el Flow actual de
  personalización por producto.

Los campos base para cargar el catálogo manualmente en Commerce Manager están
en `docs/catalogo-meta-dpikeos.csv`. Los SKU (`retailer_id`) se usan para
integración técnica y no se muestran al cliente.

## Opción 2: micrositio de pedidos DPIKEOS

Mientras Commerce Manager termina de habilitar el catálogo nativo para Cloud
API, DPIKEOS dispone de un micrositio móvil vinculado por un enlace seguro y
temporal desde WhatsApp. Es el recorrido recomendado para una compra visual:

```text
WhatsApp > Pedir ahora > Ver menú > micrositio DPIKEOS
                                      > categoría o búsqueda
                                      > producto + variación + extras
                                      > carrito > enviar pedido
                                      > panel administrativo + WhatsApp
```

Características implementadas:

- Diseño mobile-first con tarjetas de producto, buscador, categorías y carrito
  fijo.
- Personalización en una hoja/modal: variaciones y extras antes de agregar.
- Precio recalculado del lado del servidor con la configuración del producto;
  el navegador nunca decide el importe final.
- Pedido centralizado en el panel y confirmación automática por WhatsApp.
- En el panel, **Productos**, se edita el catálogo: imagen, nombre,
  descripción, precio, disponibilidad, variaciones y extras. Una opción por
  línea se ingresa como `Nombre | 4.50`.

### Operación diaria

1. Abrir **Panel > Productos**.
2. Editar el producto y cargar su foto real en `Imagen del producto`.
3. Ajustar precio, estado, variaciones y extras.
4. Guardar. El micrositio mostrará los cambios al abrirse; Meta se actualiza
   por su propia sincronización cuando se implemente la conexión API final.

Las imágenes se cargan desde el panel en cada producto. Si falta una, el bot
muestra temporalmente el logo DPIKEOS como imagen de respaldo.

## Menú conversacional actual

El menú principal evita enviar a todos los clientes al mismo recorrido:

1. **🍗 Ver menú**: ruta principal. Abre una lista de categorías; al elegir
   una, WhatsApp muestra una imagen de cabecera de esa categoría y una lista
   breve de productos. Al abrir un producto se muestra su foto, detalle,
   precio y opciones. Los SKU son internos y no se muestran al cliente.
2. **🛒 Pedido múltiple**: abre el micrositio seguro para armar un carrito con
   varios productos en una sola pantalla.
3. **📱 Catálogo WhatsApp**: queda preparado como tercer botón, pero solo se
   muestra cuando `WHATSAPP_NATIVE_CATALOG_MENU_ENABLED=true` y Meta confirme
   que el catálogo conectado al número ya está disponible.

### Imágenes hacia WhatsApp

El panel y el micrositio pueden verse en `127.0.0.1` durante desarrollo. Para
que Meta descargue imágenes y las muestre en un mensaje de WhatsApp real, la
imagen debe estar disponible en una URL pública HTTPS. En pruebas se usa la
URL HTTPS vigente de ngrok; en producción se usa el dominio HTTPS definitivo.
`127.0.0.1` solo funciona en el propio computador y Meta no puede acceder a
esa dirección.

---
