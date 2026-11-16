<?php

declare(strict_types=1);

use Obelaw\Ium\Sales\Data\CreateCustomerDto;
use Obelaw\Ium\Sales\Data\UpdateCustomerDto;
use Obelaw\Ium\Sales\Models\Customer;

it('creates a new customer via customer service', function () {
    $service = ium()->sales()->customers();

    $dto = CreateCustomerDto::from([
        'name' => 'Acme Corporation',
        'email' => 'contact@acme.com',
        'phone' => '+15551234567',
        'company' => 'Acme Corp',
        'tax_id' => 'TAX-998877',
        'currency' => 'USD',
        'price_tier' => 'wholesale',
        'billing_address' => [
            'street' => '123 Market St',
            'city' => 'New York',
            'country' => 'US',
        ],
        'shipping_address' => [
            'street' => '456 Warehouse Ave',
            'city' => 'Jersey City',
            'country' => 'US',
        ],
        'is_active' => true,
        'notes' => 'Preferred corporate client.',
        'metadata' => ['industry' => 'logistics'],
    ]);

    $customer = $service->create($dto);

    expect($customer)->toBeInstanceOf(Customer::class);
    expect($customer->name)->toBe('Acme Corporation');
    expect($customer->email)->toBe('contact@acme.com');
    expect($customer->phone)->toBe('+15551234567');
    expect($customer->company)->toBe('Acme Corp');
    expect($customer->tax_id)->toBe('TAX-998877');
    expect($customer->currency)->toBe('USD');
    expect($customer->price_tier)->toBe('wholesale');
    expect($customer->is_active)->toBeTrue();
    expect($customer->billing_address['city'])->toBe('New York');
    expect($customer->shipping_address['city'])->toBe('Jersey City');
    expect($customer->metadata['industry'])->toBe('logistics');
});

it('updates an existing customer profile', function () {
    $service = ium()->sales()->customers();

    $customer = $service->create(CreateCustomerDto::from([
        'name' => 'Original Name',
        'email' => 'original@example.com',
    ]));

    $updated = $service->update($customer->id, UpdateCustomerDto::from([
        'name' => 'Updated Name',
        'company' => 'New Company LLC',
    ]));

    expect($updated->name)->toBe('Updated Name');
    expect($updated->company)->toBe('New Company LLC');
    expect($updated->email)->toBe('original@example.com');
});

it('finds a customer by id, email, and phone', function () {
    $service = ium()->sales()->customers();

    $customer = $service->create(CreateCustomerDto::from([
        'name' => 'John Doe',
        'email' => 'johndoe@example.com',
        'phone' => '+1999888777',
    ]));

    expect($service->find($customer->id)?->name)->toBe('John Doe');
    expect($service->findByEmail('johndoe@example.com')?->id)->toBe($customer->id);
    expect($service->findByPhone('+1999888777')?->id)->toBe($customer->id);
    expect($service->findByEmail('nonexistent@example.com'))->toBeNull();
});

it('searches customers by name, company, email, or phone', function () {
    $service = ium()->sales()->customers();

    $service->create(CreateCustomerDto::from([
        'name' => 'Alice Wonder',
        'company' => 'Wonderland Ltd',
        'email' => 'alice@wonder.com',
        'phone' => '+111222333',
    ]));

    $service->create(CreateCustomerDto::from([
        'name' => 'Bob Builder',
        'company' => 'Construction Co',
        'email' => 'bob@builder.com',
        'phone' => '+444555666',
    ]));

    $results = $service->search('Wonder');
    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('Alice Wonder');

    $phoneResults = $service->search('444555');
    expect($phoneResults)->toHaveCount(1);
    expect($phoneResults->first()->name)->toBe('Bob Builder');
});

it('deletes a customer', function () {
    $service = ium()->sales()->customers();

    $customer = $service->create(CreateCustomerDto::from([
        'name' => 'Temporary Customer',
    ]));

    expect($service->find($customer->id))->not->toBeNull();

    $deleted = $service->delete($customer->id);
    expect($deleted)->toBeTrue();
    expect($service->find($customer->id))->toBeNull();
});
