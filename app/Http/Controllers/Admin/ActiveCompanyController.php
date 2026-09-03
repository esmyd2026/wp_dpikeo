<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\CompanyContext;
use Illuminate\Http\Request;

class ActiveCompanyController extends Controller
{
    /**
     * Cambia la empresa activa de la sesión del admin. El company_id que
     * llega en el request nunca se confía a ciegas: CompanyContext::switchTo
     * verifica que el usuario autenticado esté autorizado para esa empresa
     * antes de tocar la sesión.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company' => 'required|exists:companies,slug',
        ]);

        $company = Company::where('slug', $validated['company'])->firstOrFail();

        if (!CompanyContext::switchTo($company)) {
            abort(403, 'No tenés acceso a esa empresa.');
        }

        return back()->with('success', "Ahora estás administrando {$company->name}.");
    }
}
