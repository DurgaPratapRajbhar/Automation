<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;

class CustomerService
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository
    ) {}

    public function list(int $workspaceId, ?string $search, int $perPage): CursorPaginator
    {
        $perPage = max(1, min($perPage, config('pagination.max_per_page')));

        return $this->customerRepository
            ->cursorForWorkspace($workspaceId, $search, $perPage)
            ->through(fn (Customer $customer) => $this->present($customer));
    }

    public function getCustomer(int $workspaceId, int $customerId): array
    {
        return $this->present($this->findOrFail($workspaceId, $customerId));
    }

    public function create(int $workspaceId, array $data): array
    {
        $data = $this->normalizeTax($data);

        if (!empty($data['phone'])) {
            $this->ensurePhoneAvailable($workspaceId, $data['phone']);
        }

        return $this->present(
            $this->customerRepository->create([...$data, 'workspace_id' => $workspaceId])
        );
    }

    public function update(int $workspaceId, int $customerId, array $data): array
    {
        $customer = $this->findOrFail($workspaceId, $customerId);

        // Check GSTIN and state together, using the stored value for the one not sent
        if (array_key_exists('gstin', $data) || array_key_exists('state_code', $data)) {
            $data = $this->normalizeTax([
                'gstin' => $customer->gstin,
                'state_code' => $customer->state_code,
                ...$data,
            ]);
        }

        if (!empty($data['phone'])) {
            $this->ensurePhoneAvailable($workspaceId, $data['phone'], $customer->id);
        }

        return $this->present($this->customerRepository->update($customer, $data));
    }

    public function delete(int $workspaceId, int $customerId): void
    {
        // TODO when khata/invoices exist: block delete while the customer has a balance
        $this->customerRepository->delete($this->findOrFail($workspaceId, $customerId));
    }

    // The first 2 digits of a GSTIN are the state code: fill it in, or reject a mismatch
    private function normalizeTax(array $data): array
    {
        if (empty($data['gstin'])) {
            return $data;
        }

        $data['gstin'] = strtoupper($data['gstin']);
        $gstinState = substr($data['gstin'], 0, 2);

        if (empty($data['state_code'])) {
            $data['state_code'] = $gstinState;
        } elseif ($data['state_code'] !== $gstinState) {
            throw new BusinessRuleException(__('customers.gstin_state_mismatch'));
        }

        return $data;
    }

    private function ensurePhoneAvailable(int $workspaceId, string $phone, ?int $ignoreCustomerId = null): void
    {
        if ($this->customerRepository->phoneExists($workspaceId, $phone, $ignoreCustomerId)) {
            throw new BusinessRuleException(__('customers.phone_taken'));
        }
    }

    private function findOrFail(int $workspaceId, int $customerId): Customer
    {
        $customer = $this->customerRepository->findInWorkspace($workspaceId, $customerId);

        if (!$customer) {
            throw new BusinessRuleException(__('customers.not_found'), 404);
        }

        return $customer;
    }

    private function present(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'gstin' => $customer->gstin,
            'state_code' => $customer->state_code,
            'credit_limit' => $customer->credit_limit,
            'whatsapp_opt_in' => $customer->whatsapp_opt_in,
            'created_at' => $customer->created_at,
            'updated_at' => $customer->updated_at,
        ];
    }
}
