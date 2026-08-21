<!-- Modal para editar producto -->
<div id="editProductModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-lg max-w-4xl w-full">
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="text-lg font-medium text-gray-900" id="editProductModalLabel">
                    Editar Producto
                </h3>
                <button type="button" class="text-gray-400 hover:text-gray-500" onclick="closeModal('editProductModal')">
                    <span class="sr-only">Cerrar</span>
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="editProductForm" method="POST" class="p-6">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label for="edit_sku" class="block text-sm font-medium text-gray-700">SKU</label>
                            <input type="text" id="edit_sku" name="sku" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="edit_name" class="block text-sm font-medium text-gray-700">Nombre</label>
                            <input type="text" id="edit_name" name="name" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="edit_menu_item_id" class="block text-sm font-medium text-gray-700">Categoría</label>
                            <select id="edit_menu_item_id" name="menu_item_id" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->title }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="edit_price" class="block text-sm font-medium text-gray-700">Precio</label>
                            <input type="number" id="edit_price" name="price" step="0.01" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="edit_promo_price" class="block text-sm font-medium text-gray-700">Precio Promocional</label>
                            <input type="number" id="edit_promo_price" name="promo_price" step="0.01"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label for="edit_description" class="block text-sm font-medium text-gray-700">Descripción</label>
                            <textarea id="edit_description" name="description" rows="3"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                        </div>

                        <div>
                            <label for="edit_benefits" class="block text-sm font-medium text-gray-700">Beneficios</label>
                            <textarea id="edit_benefits" name="benefits" rows="3"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                        </div>

                        <div>
                            <label for="edit_nutritional_info" class="block text-sm font-medium text-gray-700">Información Nutricional</label>
                            <textarea id="edit_nutritional_info" name="nutritional_info" rows="3"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                        </div>

                        <div>
                            <label for="edit_icon" class="block text-sm font-medium text-gray-700">Icono</label>
                            <input type="text" id="edit_icon" name="icon" placeholder="Ej: 🥤"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" id="edit_is_active" name="is_active"
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="edit_is_active" class="ml-2 block text-sm text-gray-900">
                                Activo
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('editProductModal')"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Cancelar
                    </button>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-save mr-2"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editProduct(id) {
    // Obtener los datos del producto
    axios.get(`/admin/products/${id}`)
        .then(response => {
            const product = response.data;

            // Actualizar el formulario
            document.getElementById('editProductForm').action = `/admin/products/${id}`;
            document.getElementById('edit_sku').value = product.sku;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_menu_item_id').value = product.menu_item_id;
            document.getElementById('edit_price').value = product.price;
            document.getElementById('edit_promo_price').value = product.promo_price || '';
            document.getElementById('edit_description').value = product.description || '';
            document.getElementById('edit_benefits').value = product.benefits || '';
            document.getElementById('edit_nutritional_info').value = product.nutritional_info || '';
            document.getElementById('edit_icon').value = product.icon || '';
            document.getElementById('edit_is_active').checked = product.is_active;

            // Mostrar el modal
            document.getElementById('editProductModal').classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Ha ocurrido un error al cargar los datos del producto');
        });
}
</script>
