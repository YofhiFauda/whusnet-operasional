<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerDocumentController extends Controller
{
    public function store(Request $request, Customer $customer)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.documents.upload'), 403);
        $this->authorizeCustomerAccess($customer);

        $validated = $request->validate([
            'document_type' => 'required|string|in:ktp,rumah,kontrak,survey,pemasangan',
            'document_file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:4096',
        ]);

        $path = $request->file('document_file')->store(
            'customer-documents/' . $customer->id,
            'local'
        );

        $document = CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type' => $validated['document_type'],
            'file_path' => $path,
            'uploaded_by' => auth()->id(),
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Dokumen Pelanggan',
            'action' => 'upload',
            'auditable_type' => CustomerDocument::class,
            'auditable_id' => $document->id,
            'old_values' => null,
            'new_values' => [
                'customer_id' => $document->customer_id,
                'document_type' => $document->document_type,
                'file_path' => $document->file_path,
                'uploaded_by' => $document->uploaded_by,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return redirect()->route('customers.show', $customer->id)
            ->with('success', 'Dokumen pelanggan berhasil diupload.');
    }

    public function show(CustomerDocument $document): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.documents.view'), 403);
        $this->authorizeCustomerAccess($document->customer);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response($document->file_path);
    }

    private function authorizeCustomerAccess(Customer $customer): void
    {
        abort_unless(Customer::query()->applyUserScope()->whereKey($customer->id)->exists(), 403);
    }
}
