<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\AssetContainer;
use Statamic\Fields\Field;
use Sushidev\Fairu\Fieldtypes\Fairu;
use Sushidev\Fairu\Fieldtypes\FolderSelector;
use Sushidev\Fairu\Fieldtypes\ImageRule;
use Sushidev\Fairu\Fieldtypes\MaxFilesizeRule;
use Sushidev\Fairu\Fieldtypes\MinFilesizeRule;
use Sushidev\Fairu\Fieldtypes\MimesRule;
use Sushidev\Fairu\Fieldtypes\MimetypesRule;

function fairuField(array $config = []): Fairu
{
    return (new Fairu())->setField(new Field('images', array_merge(['type' => 'fairu'], $config)));
}

it('normalizes field values and publishes configuration', function () {
    $field = fairuField(['max_files' => 1, 'min_files' => 1, 'folder' => 'folder']);
    expect($field->defaultValue())->toBeNull()->and($field->preProcess(null))->toBeNull()
        ->and($field->preProcess(TEMPLATE_ID))->toBe([TEMPLATE_ID])
        ->and($field->process([]))->toBeNull()->and($field->process([TEMPLATE_ID]))->toBe(TEMPLATE_ID)
        ->and(fairuField()->process([TEMPLATE_ID]))->toBe([TEMPLATE_ID])
        ->and($field->rules())->toBe(['array', 'max:1', 'min:1'])
        ->and($field->getItemData(['id']))->toBe(['id'])
        ->and($field->augment([TEMPLATE_ID]))->toBe([TEMPLATE_ID])
        ->and($field->preload()['folder'])->toBe('folder')
        ->and($field->icon())->toContain('<svg')
        ->and(invokeFairu($field, 'configFieldItems'))->toHaveKeys(['max_files', 'min_files', 'folder', 'display_type']);
    $folder = new FolderSelector();
    expect($folder->defaultValue())->toBe('')->and($folder->preProcess('folder'))->toBe('folder')
        ->and($folder->process('folder'))->toBe('folder')->and($folder->preload())->toHaveKeys(['proxy', 'file']);
});

it('converts legacy field paths', function () {
    Storage::fake('legacy');
    $container = AssetContainer::make('legacy')->disk('legacy');
    AssetContainer::shouldReceive('findByHandle')->with('legacy')->andReturn($container);
    $expected = (new \Sushidev\Fairu\Services\Fairu())->convertToUuid(Storage::disk('legacy')->url('image.webp'));
    expect(fairuField(['container' => 'legacy'])->preProcess(['image.webp']))->toBe([$expected]);
});

it('augments fields with metadata and supports single selections', function ($max) {
    Http::fake(['*/api/files/meta' => Http::response(templateMeta())]);
    $out = fairuField(['max_files' => $max])->shallowAugment([TEMPLATE_ID]);
    $asset = $max === 1 ? $out : $out[0];
    expect($asset['url'])->toContain('hero.webp')->and($asset['focus_css'])->toBe('40% 30%');
})->with([1, 5]);

it('maps asset validation rules while preserving ordinary rules', function () {
    $rules = fairuField(['validate' => ['image', 'max_filesize:100', 'mimes:jpg', 'mimetypes:image/*', 'min_filesize:1', 'required']])->fieldRules();
    expect(array_map(fn ($rule) => is_object($rule) ? get_class($rule) : $rule, $rules))->toBe([
        ImageRule::class, MaxFilesizeRule::class, MimesRule::class, MimetypesRule::class, MinFilesizeRule::class, 'required',
    ]);
});

dataset('asset rules', [
    [ImageRule::class, []], [MimesRule::class, ['jpg']], [MimetypesRule::class, ['image/*']],
    [MaxFilesizeRule::class, [2]], [MinFilesizeRule::class, [1]],
]);

it('validates every selected asset and produces a message', function ($class, $params, $scenario) {
    $files = match ($scenario) {
        'empty-result' => [],
        'missing-id' => [['id' => 'different', 'mime' => 'image/jpeg', 'size' => 1024]],
        'missing-mime' => [['id' => TEMPLATE_ID, 'size' => 1024]],
        'valid', 'empty-input' => [['id' => TEMPLATE_ID, 'mime' => 'image/jpeg', 'size' => 1024]],
        'invalid' => [['id' => TEMPLATE_ID, 'mime' => 'application/pdf', 'size' => $class === MinFilesizeRule::class ? 0 : 4096]],
    };
    Http::fake(['*/api/files/list' => Http::response($files)]);
    $rule = new $class($params);
    $sizeRule = in_array($class, [MaxFilesizeRule::class, MinFilesizeRule::class]);
    $expected = in_array($scenario, ['valid', 'empty-input']) || ($scenario === 'missing-mime' && $sizeRule);
    expect($rule->passes('images', $scenario === 'empty-input' ? [] : [TEMPLATE_ID]))->toBe($expected)
        ->and($rule->message())->toBeString()->not->toBe('');
})->with('asset rules')->with(['valid', 'invalid', 'empty-input', 'empty-result', 'missing-id', 'missing-mime']);
