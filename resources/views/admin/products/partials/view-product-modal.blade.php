<!-- Modal para ver detalles del producto -->
<div id="viewProductModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden" aria-labelledby="viewProductModalLabel" aria-hidden="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-lg max-w-4xl w-full">
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="text-lg font-medium text-gray-900" id="viewProductModalLabel">
                    Detalles del Producto
                </h3>
                <button type="button" class="text-gray-400 hover:text-gray-500" onclick="closeModal('viewProductModal')">
                    <span class="sr-only">Cerrar</span>
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h4 class="text-sm font-medium text-gray-900">Información Básica</h4>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">SKU</dt>
                                <dd class="mt-1 text-sm text-gray-900" id="view_sku"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Nombre</dt>
                                <dd class="mt-1 text-sm text-gray-900" id="view_name"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Categoría</dt>
                                <dd class="mt-1 text-sm text-gray-900" id="view_category"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Precio</dt>
                                <dd class="mt-1 text-sm text-gray-900" id="view_price"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Precio Promocional</dt>
                                <dd class="mt-1 text-sm text-gray-900" id="view_promo_price"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Estado</dt>
                                <dd class="mt-1" id="view_status"></dd>
                            </div>
                        </dl>
                    </div>

                    <div class="space-y-4">
                        <h4 class="text-sm font-medium text-gray-900">Detalles Adicionales</h4>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Descripción</dt>
                                <dd class="mt-1 text-sm text-gray-900" id="view_description"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Beneficios</dt>
                                <dd class="mt-1 text-sm text-gray-900" id="view_benefits"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Información Nutricional</dt>
                                <dd class="mt-1 text-sm text-gray-900" id="view_nutritional_info"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Icono</dt>
                                <dd class="mt-1 text-sm text-gray-900" id="view_icon"></dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="button" onclick="closeModal('viewProductModal')"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewProduct(id) {
    // Obtener los datos del producto
    axios.get(`/admin/products/${id}`)
        .then(response => {
            const product = response.data;

            // Actualizar el contenido del modal
            document.getElementById('view_sku').textContent = product.sku;
            document.getElementById('view_name').textContent = product.name;
            document.getElementById('view_category').textContent = product.menu_item.title;
            document.getElementById('view_price').textContent = `$${parseFloat(product.price).toFixed(2)}`;
            document.getElementById('view_promo_price').textContent = product.promo_price ? `$${parseFloat(product.promo_price).toFixed(2)}` : '-';
            document.getElementById('view_status').innerHTML = `
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${product.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                    ${product.is_active ? 'Activo' : 'Inactivo'}
                </span>
            `;
            document.getElementById('view_description').textContent = product.description || '-';
            document.getElementById('view_benefits').textContent = product.benefits || '-';
            document.getElementById('view_nutritional_info').textContent = product.nutritional_info || '-';
            document.getElementById('view_icon').textContent = product.icon || '-';

            // Mostrar el modal
            document.getElementById('viewProductModal').classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Ha ocurrido un error al cargar los datos del producto');
        });
}
</script>
