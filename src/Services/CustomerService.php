<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Obelaw\Ium\Sales\Data\CreateCustomerDto;
use Obelaw\Ium\Sales\Data\UpdateCustomerDto;
use Obelaw\Ium\Sales\Models\Customer;

/**
 * Customer Directory & Commercial Profile Service.
 */
final class CustomerService
{
    /**
     * Create a new customer profile.
     */
    public function create(CreateCustomerDto $dto): Customer
    {
        return Customer::create($dto->toArray());
    }

    /**
     * Update an existing customer profile.
     *
     * @param int|Customer $customer
     * @param UpdateCustomerDto|array<string, mixed> $data
     */
    public function update(int|Customer $customer, UpdateCustomerDto|array $data): Customer
    {
        $model = $customer instanceof Customer ? $customer : Customer::findOrFail($customer);
        $attributes = $data instanceof UpdateCustomerDto ? $data->toArray() : $data;

        $model->update($attributes);

        return $model->fresh();
    }

    /**
     * Find a customer by ID.
     */
    public function find(int $id): ?Customer
    {
        return Customer::find($id);
    }

    /**
     * Find a customer by primary email.
     */
    public function findByEmail(string $email): ?Customer
    {
        return Customer::where('email', $email)->first();
    }

    /**
     * Find a customer by phone number.
     */
    public function findByPhone(string $phone): ?Customer
    {
        return Customer::where('phone', $phone)->first();
    }

    /**
     * Delete a customer profile.
     *
     * @param int|Customer $customer
     */
    public function delete(int|Customer $customer): bool
    {
        $model = $customer instanceof Customer ? $customer : Customer::find($customer);

        if ($model === null) {
            return false;
        }

        return (bool) $model->delete();
    }

    /**
     * Retrieve all customers.
     *
     * @return Collection<int, Customer>
     */
    public function all(): Collection
    {
        return Customer::all();
    }

    /**
     * Paginate customers.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Customer::latest('id')->paginate($perPage);
    }

    /**
     * Search customers by name, company, email, or phone.
     *
     * @return Collection<int, Customer>
     */
    public function search(string $query): Collection
    {
        $searchTerm = '%' . trim($query) . '%';

        return Customer::where(function ($q) use ($searchTerm) {
            $q->where('name', 'like', $searchTerm)
                ->orWhere('company', 'like', $searchTerm)
                ->orWhere('email', 'like', $searchTerm)
                ->orWhere('phone', 'like', $searchTerm);
        })->get();
    }
}
