<?php

declare(strict_types=1);

use Rundesk\Extension\Sdk\Abstract\BaseModel;

// Concrete test models
class TestModelA extends BaseModel
{
    protected $table = 'model_a';
}

class TestModelB extends BaseModel
{
    protected $table = 'model_b';
}

beforeEach(function (): void {
    // Reset static state between tests
    $ref = new ReflectionClass(BaseModel::class);

    $mapProp = $ref->getProperty('connectionMap');
    $mapProp->setAccessible(true);
    $mapProp->setValue(null, []);

    BaseModel::clearDefaultConnection();
});

test('setExtensionConnection sets per-class connection', function (): void {
    TestModelA::setExtensionConnection('ext_a');

    $model = new TestModelA;

    expect($model->getConnectionName())->toBe('ext_a');
});

test('per-class connections are isolated between models', function (): void {
    TestModelA::setExtensionConnection('ext_a');
    TestModelB::setExtensionConnection('ext_b');

    expect((new TestModelA)->getConnectionName())->toBe('ext_a');
    expect((new TestModelB)->getConnectionName())->toBe('ext_b');
});

test('getConnectionName returns null when no connection set', function (): void {
    expect((new TestModelA)->getConnectionName())->toBeNull();
});

test('setDefaultConnection provides fallback for all subclasses', function (): void {
    BaseModel::setDefaultConnection('ext_default');

    expect((new TestModelA)->getConnectionName())->toBe('ext_default');
    expect((new TestModelB)->getConnectionName())->toBe('ext_default');
});

test('per-class connection takes priority over default', function (): void {
    BaseModel::setDefaultConnection('ext_default');
    TestModelA::setExtensionConnection('ext_a');

    expect((new TestModelA)->getConnectionName())->toBe('ext_a');
    expect((new TestModelB)->getConnectionName())->toBe('ext_default');
});

test('clearDefaultConnection removes the fallback', function (): void {
    BaseModel::setDefaultConnection('ext_default');
    BaseModel::clearDefaultConnection();

    expect((new TestModelA)->getConnectionName())->toBeNull();
});
