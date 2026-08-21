<!-- Modal para crear producto -->
<div id="createProductModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden" aria-labelledby="createProductModalLabel" aria-hidden="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-lg max-w-4xl w-full">
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="text-lg font-medium text-gray-900" id="createProductModalLabel">
                    Crear Nuevo Producto
                </h3>
                <button type="button" class="text-gray-400 hover:text-gray-500" onclick="closeModal('createProductModal')">
                    <span class="sr-only">Cerrar</span>
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form action="{{ route('admin.products.store') }}" method="POST" class="p-6">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label for="sku" class="block text-sm font-medium text-gray-700">SKU</label>
                            <input type="text" id="sku" name="sku" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Nombre</label>
                            <input type="text" id="name" name="name" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="menu_item_id" class="block text-sm font-medium text-gray-700">Categoría</label>
                            <select id="menu_item_id" name="menu_item_id" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->title }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="price" class="block text-sm font-medium text-gray-700">Precio</label>
                            <input type="number" id="price" name="price" step="0.01" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="promo_price" class="block text-sm font-medium text-gray-700">Precio Promocional</label>
                            <input type="number" id="promo_price" name="promo_price" step="0.01"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">Descripción</label>
                            <textarea id="description" name="description" rows="3"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                        </div>

                        <div>
                            <label for="benefits" class="block text-sm font-medium text-gray-700">Beneficios</label>
                            <textarea id="benefits" name="benefits" rows="3"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                        </div>

                        <div>
                            <label for="nutritional_info" class="block text-sm font-medium text-gray-700">Información Nutricional</label>
                            <textarea id="nutritional_info" name="nutritional_info" rows="3"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                        </div>

                        <div>
                            <label for="icon" class="block text-sm font-medium text-gray-700">Icono</label>
                            <input type="text" id="icon" name="icon" placeholder="Ej: 🥤"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" id="is_active" name="is_active" checked
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="is_active" class="ml-2 block text-sm text-gray-900">
                                Activo
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('createProductModal')"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Cancelar
                    </button>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-save mr-2"></i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function showCreateProductModal() {
    document.getElementById('createProductModal').classList.remove('hidden');
}
</script>
