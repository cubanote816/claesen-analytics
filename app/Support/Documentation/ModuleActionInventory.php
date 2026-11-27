<?php

declare(strict_types=1);

namespace App\Support\Documentation;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Throwable;

/**
 * Every Filament action a module shows, as the guide anchor that must document it (CLA-693, CLA-728).
 *
 * The guide coverage test (tests/Feature/Documentation/ModuleGuideCoverageTest) fails when an
 * action has no section, so a new button cannot ship without its documentation.
 *
 * The inventory reads the panels, not the source text: it takes every resource and page registered
 * on a panel whose class file lives in the module (or in an extra root a guide lists in its
 * `filament` front matter, see ModuleGuide::filamentRoots()), builds its header actions, tables,
 * forms and infolists on an unmounted page, and keeps the real action names. Only hooks the module
 * itself declares are read, so Filament's own defaults (the edit page's save and cancel) are not.
 *
 * The anchor is `action-<scope>-<name>`:
 *
 *   CampaignResource (its pages, table, form, infolist) → campaign
 *   CampaignResource relation MessagesRelationManager    → campaign-messages
 *   Pages/BiConfigPage                                    → bi-config-page
 *
 * so the same button on a list row and on the record page is one section. Filament names
 * DeleteBulkAction `delete`, like DeleteAction; a bulk action gets `_bulk` so the two keep their
 * own sections. In schemas only actions placed on purpose count (an Actions row, hint, prefix and
 * suffix actions), not the controls a component brings itself (a repeater's add button).
 *
 * A screen that cannot be built on an unmounted page throws, naming the screen: a coverage gap is
 * never silent.
 */
final class ModuleActionInventory
{
    /** @var list<string> */
    private array $roots;

    /** @var array<string, list<string>> */
    private array $anchors = [];

    /** @var list<string> */
    private array $errors = [];

    /** @param  list<string>  $roots */
    private function __construct(array $roots)
    {
        $this->roots = array_map(fn (string $root): string => rtrim((string) (realpath($root) ?: $root), '/').'/', $roots);
    }

    /**
     * @param  list<string>  $extraRoots  absolute folders outside the module (ModuleGuide::filamentRoots())
     * @param  list<Panel>|null  $panels  the panels to read; every registered panel when null (tests pass a fixture panel)
     * @return array<string, list<string>> anchor => where it is declared (class basename and part), sorted by anchor
     */
    public static function anchors(string $moduleDirectory, array $extraRoots = [], ?array $panels = null): array
    {
        $inventory = new self([$moduleDirectory, ...$extraRoots]);
        $current = Filament::getCurrentPanel();

        try {
            foreach ($panels ?? Filament::getPanels() as $panel) {
                $inventory->readPanel($panel);
            }
        } finally {
            Filament::setCurrentPanel($current);
        }

        if ($inventory->errors !== []) {
            throw new RuntimeException("These screens could not be read for the guide coverage:\n- ".implode("\n- ", array_unique($inventory->errors)));
        }

        ksort($inventory->anchors);

        return array_map(fn (array $where): array => array_values(array_unique($where)), $inventory->anchors);
    }

    private function readPanel(Panel $panel): void
    {
        Filament::setCurrentPanel($panel);

        foreach ($panel->getResources() as $resource) {
            if ($this->owns($resource)) {
                $this->attempt($resource, fn () => $this->readResource($resource));
            }
        }

        foreach ($panel->getPages() as $page) {
            if ($this->owns($page)) {
                $this->attempt($page, fn () => $this->readPage(Str::kebab(class_basename($page)), app($page)));
            }
        }
    }

    /** @param  class-string<\Filament\Resources\Resource>  $resource */
    private function readResource(string $resource): void
    {
        $scope = Str::kebab(Str::singular(Str::beforeLast(class_basename($resource), 'Resource')));

        foreach ($resource::getPages() as $registration) {
            $pageClass = $registration->getPage();
            $page = app($pageClass);

            $this->readPage($scope, $page);

            if (is_subclass_of($pageClass, ListRecords::class) && $this->declares($resource, 'table')) {
                $this->add($scope, $this->tableActions($resource::table(Table::make($page))), class_basename($resource).' table');
            }
            if (is_subclass_of($pageClass, ViewRecord::class) && $this->declares($resource, 'infolist')) {
                $this->add($scope, $this->schemaActions($resource::infolist(Schema::make($page))), class_basename($resource).' infolist');
            }
            if ((is_subclass_of($pageClass, CreateRecord::class) || is_subclass_of($pageClass, EditRecord::class)) && $this->declares($resource, 'form')) {
                $this->add($scope, $this->schemaActions($resource::form(Schema::make($page))), class_basename($resource).' form');
            }
        }

        foreach ($resource::getRelations() as $relation) {
            $managers = $relation instanceof RelationGroup ? $relation->getManagers() : [$relation];

            // A manager is a class name or, through RelationManager::make([...]), a configuration
            // object whose properties shape the table, so they are applied before it is built.
            foreach ($managers as $configured) {
                $manager = $configured instanceof RelationManagerConfiguration ? $configured->relationManager : $configured;
                $properties = $configured instanceof RelationManagerConfiguration ? $configured->getProperties() : [];

                $this->attempt($manager, function () use ($scope, $manager, $properties): void {
                    $instance = app($manager);
                    foreach ($properties as $property => $value) {
                        $instance->{$property} = $value;
                    }
                    $relationScope = $scope.'-'.Str::kebab(Str::beforeLast(class_basename($manager), 'RelationManager'));
                    $this->add($relationScope, $this->tableActions($instance->table(Table::make($instance))), class_basename($manager));
                });
            }
        }
    }

    /** Header actions, footer form actions, its own form and table: whatever the module declares. */
    private function readPage(string $scope, object $page): void
    {
        $where = class_basename($page);

        foreach (['getHeaderActions', 'getFormActions'] as $hook) {
            if ($this->declares($page::class, $hook)) {
                $this->add($scope, $this->flatten($this->invoke($page, $hook)), $where);
            }
        }

        foreach (['form', 'infolist'] as $schema) {
            if ($this->declares($page::class, $schema) && ! (new ReflectionMethod($page, $schema))->isStatic()) {
                $this->add($scope, $this->schemaActions($page->{$schema}(Schema::make($page))), $where.' '.$schema);
            }
        }
        if ($this->declares($page::class, 'table') && ! (new ReflectionMethod($page, 'table'))->isStatic()) {
            $this->add($scope, $this->tableActions($page->table(Table::make($page))), $where.' table');
        }
    }

    /** @return list<string> */
    private function tableActions(Table $table): array
    {
        return $this->flatten([...$table->getHeaderActions(), ...$table->getRecordActions(), ...$table->getToolbarActions(), ...$table->getBulkActions()]);
    }

    /** @return list<string> actions placed on purpose: Actions rows and hint, prefix and suffix actions */
    private function schemaActions(Schema $schema): array
    {
        $names = [];

        foreach ($schema->getComponents(withHidden: true) as $component) {
            if (! $component instanceof Component) {
                continue;
            }
            if ($component instanceof Actions) {
                // Hidden ones too: an action shown only in some state still needs its section.
                $children = $component->getDefaultChildComponents();
                $names = [...$names, ...$this->flatten($children instanceof Schema ? $children->getComponents(withHidden: true) : $children)];
            }
            foreach (['getHintActions', 'getPrefixActions', 'getSuffixActions'] as $method) {
                if (method_exists($component, $method)) {
                    $names = [...$names, ...$this->flatten($component->{$method}())];
                }
            }
            foreach ($component->getChildSchemas(withHidden: true) ?? [] as $child) {
                $names = [...$names, ...$this->schemaActions($child)];
            }
        }

        return $names;
    }

    /** @return list<string> */
    private function flatten(array $actions): array
    {
        $names = [];

        foreach ($actions as $action) {
            if ($action instanceof ActionGroup) {
                $names = [...$names, ...$this->flatten(array_values($action->getFlatActions()))];
            } elseif ($action instanceof Action && ($name = $action->getName()) !== null) {
                $names[] = $action->isBulk() && ! str_ends_with($name, '_bulk') ? $name.'_bulk' : $name;
            }
        }

        return $names;
    }

    /** @param  list<string>  $names */
    private function add(string $scope, array $names, string $where): void
    {
        foreach ($names as $name) {
            $this->anchors['action-'.$scope.'-'.$name][] = $where;
        }
    }

    private function attempt(string $class, callable $read): void
    {
        try {
            $read();
        } catch (Throwable $e) {
            $this->errors[] = class_basename($class).' ('.$class.'): '.$e::class.': '.Str::limit($e->getMessage(), 200);
        }
    }

    private function invoke(object $page, string $method): array
    {
        $reflection = new ReflectionMethod($page, $method);
        $reflection->setAccessible(true);

        return (array) $reflection->invoke($page);
    }

    /** The class lives in the module or an extra root. */
    private function owns(string $class): bool
    {
        return $this->inRoots((string) (new ReflectionClass($class))->getFileName());
    }

    /** The method is declared (or overridden) by module code, not inherited from Filament. */
    private function declares(string $class, string $method): bool
    {
        return method_exists($class, $method)
            && $this->inRoots((string) (new ReflectionMethod($class, $method))->getDeclaringClass()->getFileName());
    }

    private function inRoots(string $file): bool
    {
        foreach ($this->roots as $root) {
            if (str_starts_with($file, $root)) {
                return true;
            }
        }

        return false;
    }
}
