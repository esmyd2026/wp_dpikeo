<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessFaq;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Preguntas frecuentes que el bot muestra en "Información" (ver
 * WhatsappService::getInfoMenu()). Mismo patrón que BusinessBranchController:
 * un solo listado con formularios embebidos, sin pantallas de crear/editar
 * aparte.
 */
class FaqController extends Controller
{
    public function index(): View
    {
        $businessProfileId = CompanyContext::current()->businessProfileId();

        return view('admin.faqs.index', [
            'faqs' => BusinessFaq::query()
                ->when($businessProfileId, fn ($q) => $q->where('business_profile_id', $businessProfileId))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = CompanyContext::current()->businessProfile;
        abort_unless($profile, 422, 'Esta empresa todavía no tiene un número de WhatsApp conectado.');
        $data = $this->validated($request);

        BusinessFaq::create(array_merge($data, ['business_profile_id' => $profile->id]));

        return back()->with('success', 'Pregunta frecuente creada.');
    }

    public function update(Request $request, BusinessFaq $faq): RedirectResponse
    {
        $this->authorizeFaq($faq);
        $faq->update($this->validated($request));

        return back()->with('success', 'Pregunta frecuente actualizada.');
    }

    public function destroy(BusinessFaq $faq): RedirectResponse
    {
        $this->authorizeFaq($faq);
        $faq->delete();

        return back()->with('success', 'Pregunta frecuente eliminada.');
    }

    /** El id de la FAQ llega en la URL: sin este chequeo se podría editar/borrar la de otra empresa. */
    private function authorizeFaq(BusinessFaq $faq): void
    {
        $businessProfileId = CompanyContext::current()->businessProfileId();
        abort_unless($businessProfileId && (int) $faq->business_profile_id === (int) $businessProfileId, 404);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:200'],
            'answer' => ['required', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'question' => trim($data['question']),
            'answer' => trim($data['answer']),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
