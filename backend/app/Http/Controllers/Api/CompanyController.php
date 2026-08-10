<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $query = Company::withCount(['clients', 'tasks']);

        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }
        if ($request->has('status')) {
            $active = filter_var($request->status, FILTER_VALIDATE_BOOL);
            $query->where('is_active', $active);
        }

        return response()->json($query->orderBy('name')->paginate($request->get('per_page', 15)));
    }

    public function show(Company $company)
    {
        return response()->json($company->loadCount(['clients', 'tasks']));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:companies,code',
            'logo' => 'nullable|string',
            'description' => 'nullable|string',
            'settings' => 'nullable|array',
        ]);

        $company = Company::create($request->only(['name', 'code', 'logo', 'description', 'settings']));

        return response()->json($company, 201);
    }

    public function update(Request $request, Company $company)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('companies', 'code')->ignore($company->id)],
            'logo' => 'nullable|string',
            'description' => 'nullable|string',
            'settings' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $company->update($request->only(['name', 'code', 'logo', 'description', 'settings', 'is_active']));

        return response()->json($company);
    }

    public function destroy(Company $company)
    {
        $company->delete();
        return response()->json(['message' => 'Empresa eliminada']);
    }

    public function stats(Company $company)
    {
        return response()->json([
            'id' => $company->id,
            'name' => $company->name,
            'code' => $company->code,
            'clients_count' => $company->clients()->count(),
            'tasks_count' => $company->tasks()->count(),
            'completed_tasks' => $company->tasks()->where('status', 'completed')->count(),
            'pending_tasks' => $company->tasks()->whereIn('status', ['pending', 'assigned'])->count(),
            'failed_tasks' => $company->tasks()->where('status', 'failed')->count(),
        ]);
    }
}