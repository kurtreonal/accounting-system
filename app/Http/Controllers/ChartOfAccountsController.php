<?php

namespace App\Http\Controllers;

use App\Services\DemoData\AccountDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class ChartOfAccountsController extends Controller
{
    public function store(Request $request, AccountDataService $accounts): JsonResponse
    {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }

        $validator = Validator::make($request->all(), $this->accountRules(includeStatus: true, includeOpeningBalance: true));

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $account = $accounts->create($this->normalized($validator->validated(), includeStatus: true, includeOpeningBalance: true));
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Account added successfully.', 'account' => $account], 201);
    }

    public function update(Request $request, string $code, AccountDataService $accounts): JsonResponse
    {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }

        $validator = Validator::make($request->all(), $this->accountRules(includeStatus: false, includeOpeningBalance: false));

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $account = $accounts->update($code, $this->normalized($validator->validated(), includeStatus: false, includeOpeningBalance: false));
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Account updated successfully.', 'account' => $account]);
    }

    public function status(Request $request, string $code, AccountDataService $accounts): JsonResponse
    {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $account = $accounts->updateStatus($code, $validator->validated()['status']);
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Account status updated successfully.', 'account' => $account]);
    }

    public function destroy(Request $request, string $code, AccountDataService $accounts): JsonResponse
    {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }

        try {
            $accounts->delete($code);
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Account deleted successfully.']);
    }

    /** @return array<string, array<int, mixed>> */
    private function accountRules(bool $includeStatus, bool $includeOpeningBalance): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'])],
            'sub_type' => ['nullable', 'string', 'max:100'],
        ];

        if ($includeOpeningBalance) {
            $rules['balance'] = ['nullable', 'numeric', 'between:0,0'];
        }

        if ($includeStatus) {
            $rules['status'] = ['required', Rule::in(['Active', 'Inactive'])];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalized(array $validated, bool $includeStatus, bool $includeOpeningBalance): array
    {
        $account = [
            'name' => trim($validated['name']),
            'type' => $validated['type'],
            'sub_type' => trim((string) ($validated['sub_type'] ?? '')),
        ];

        if ($includeOpeningBalance) {
            $account['balance'] = 0;
        }

        if ($includeStatus) {
            $account['status'] = $validated['status'];
        }

        return $account;
    }

    private function denyMutation(Request $request): ?JsonResponse
    {
        if (! $request->session()->has('demo_user')) {
            return response()->json(['message' => 'Authentication is required.'], 401);
        }

        if ($request->session()->get('demo_user.role') === 'Viewer / Auditor') {
            return response()->json(['message' => 'This demo role has read-only access.'], 403);
        }

        return null;
    }

    /** @param array<string, array<int, string>> $errors */
    private function validationError(array $errors): JsonResponse
    {
        return response()->json([
            'message' => 'Please review the highlighted fields.',
            'errors' => $errors,
        ], 422);
    }

    private function persistenceError(RuntimeException $exception): JsonResponse
    {
        if ($exception->getMessage() === 'The account could not be found.') {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        report($exception);

        return response()->json(['message' => 'Unable to save the account data. Please try again.'], 500);
    }
}
