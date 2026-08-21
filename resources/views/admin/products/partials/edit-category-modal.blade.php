<!-- Modal para editar categoría -->
<div id="editCategoryModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-lg max-w-2xl w-full">
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="text-lg font-medium text-gray-900" id="editCategoryModalLabel">
                    Editar Categoría
                </h3>
                <button type="button" class="text-gray-400 hover:text-gray-500" onclick="closeModal('editCategoryModal')">
                    <span class="sr-only">Cerrar</span>
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="editCategoryForm" method="POST" class="p-6">
                @csrf
                @method('PUT')
                <div class="space-y-4">
                    <div>
                        <label for="edit_title" class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input type="text" id="edit_title" name="title" required
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>

                    <div>
                        <label for="edit_description" class="block text-sm font-medium text-gray-700">Descripción</label>
                        <textarea id="edit_description" name="description" rows="3"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" id="edit_is_active" name="is_active"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="edit_is_active" class="ml-2 block text-sm text-gray-900">
                            Activo
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('editCategoryModal')"
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
function editCategory(id) {
    // Obtener los datos de la categoría
    axios.get(`/admin/categories/${id}`)
        .then(response => {
            const category = response.data;

            // Actualizar el formulario
            document.getElementById('editCategoryForm').action = `/admin/categories/${id}`;
            document.getElementById('edit_title').value = category.title;
            document.getElementById('edit_description').value = category.description || '';
            document.getElementById('edit_is_active').checked = category.is_active;

            // Mostrar el modal
            document.getElementById('editCategoryModal').classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Ha ocurrido un error al cargar los datos de la categoría');
        });
}
</script>
