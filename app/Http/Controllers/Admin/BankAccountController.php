<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request; // Import Request
use Illuminate\Validation\Rule; // Import Rule for validation

class BankAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Fetch all bank accounts, newest first
        $bankAccounts = BankAccount::latest()->paginate(15); // Paginate for better performance
        return view('admin.bank_accounts.index', compact('bankAccounts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Pass an empty BankAccount object to the form (optional, helps with form structure)
        $bankAccount = new BankAccount(['is_active' => true]); // Default to active
        return view('admin.bank_accounts.create', compact('bankAccount'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validate the incoming request data
        $validatedData = $request->validate([
            'bank_name_ar' => 'required|string|max:255',
            'bank_name_en' => 'nullable|string|max:255',
            'account_name_ar' => 'required|string|max:255',
            'account_name_en' => 'nullable|string|max:255',
            'account_number' => 'required|string|max:50',
            // Add basic IBAN validation (adjust regex if needed for Saudi Arabia)
            'iban' => ['required', 'string', 'max:34', Rule::unique('bank_accounts')],
            'is_active' => 'sometimes|boolean', // Use boolean validation
        ]);

        // Handle the checkbox value for is_active
        $validatedData['is_active'] = $request->has('is_active');

        // Create the bank account
        BankAccount::create($validatedData);

        // Redirect back to the index page with a success message
        return redirect()->route('admin.bank-accounts.index')
                         ->with('success', 'تم إضافة الحساب البنكي بنجاح.');
    }

    /**
     * Display the specified resource.
     * Note: We typically don't need a dedicated 'show' view for admin CRUD.
     * The 'edit' view serves a similar purpose.
     * You can remove this method if not needed.
     */
    public function show(BankAccount $bankAccount)
    {
        // If you need a separate view page for details, implement it here.
        // Otherwise, redirect to edit or index.
        return redirect()->route('admin.bank-accounts.edit', $bankAccount);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BankAccount $bankAccount)
    {
        // Pass the existing bank account data to the view
        return view('admin.bank_accounts.edit', compact('bankAccount'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BankAccount $bankAccount)
    {
        // Validate the incoming request data
        $validatedData = $request->validate([
            'bank_name_ar' => 'required|string|max:255',
            'bank_name_en' => 'nullable|string|max:255',
            'account_name_ar' => 'required|string|max:255',
            'account_name_en' => 'nullable|string|max:255',
            'account_number' => 'required|string|max:50',
            // Ensure IBAN is unique, ignoring the current bank account's IBAN
            'iban' => ['required', 'string', 'max:34', Rule::unique('bank_accounts')->ignore($bankAccount->id)],
            'is_active' => 'sometimes|boolean', // Use boolean validation
        ]);

        // Handle the checkbox value for is_active
        $validatedData['is_active'] = $request->has('is_active');

        // Update the bank account
        $bankAccount->update($validatedData);

        // Redirect back to the index page with a success message
        return redirect()->route('admin.bank-accounts.index')
                         ->with('success', 'تم تحديث الحساب البنكي بنجاح.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BankAccount $bankAccount)
    {
        // Delete the bank account
        try {
            $bankAccount->delete();
            return redirect()->route('admin.bank-accounts.index')
                             ->with('success', 'تم حذف الحساب البنكي بنجاح.');
        } catch (\Exception $e) {
            // Handle potential errors (e.g., foreign key constraints if linked elsewhere)
            return redirect()->route('admin.bank-accounts.index')
                             ->with('error', 'حدث خطأ أثناء محاولة حذف الحساب البنكي.');
        }
    }

     /**
      * Toggle the active status of the bank account.
      * (Optional - for the extra route we defined)
      */
    public function toggleActive(BankAccount $bankAccount)
    {
        $bankAccount->update(['is_active' => !$bankAccount->is_active]);

        $message = $bankAccount->is_active ? 'تم تفعيل الحساب البنكي.' : 'تم تعطيل الحساب البنكي.';

        return redirect()->back()->with('success', $message);
    }
}