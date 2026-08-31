<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../controllers/CustomReportController.php';

class CustomReportPageSizeTest extends TestCase
{
    private function call(string $method, array $args): array
    {
        $controller = (new ReflectionClass(CustomReportController::class))
            ->newInstanceWithoutConstructor();
        $reflection = new ReflectionMethod(CustomReportController::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($controller, $args);
    }

    private function callAttachPageState(array $view, array $raw): array
    {
        return $this->call('attachPageState', [$view, $raw, 'table']);
    }

    public function testTableUsesABrowserSafeDefaultPageSize(): void
    {
        $state = $this->callAttachPageState([], []);

        $this->assertSame(100, $state['perPage']);
        $this->assertSame(1, $state['page']);
    }

    public function testLegacyOversizedPageSizeFallsBackToSafeDefault(): void
    {
        $state = $this->callAttachPageState([], ['perPage' => 1000, 'page' => 2]);

        $this->assertSame(100, $state['perPage']);
        $this->assertSame(2, $state['page']);
    }

    public function testSupportedTablePageSizesRemainAvailable(): void
    {
        foreach ([50, 100, 200] as $size) {
            $state = $this->callAttachPageState([], ['perPage' => $size]);
            $this->assertSame($size, $state['perPage']);
        }
    }

    public function testTableViewLimitsVisibleColumnsToPreventBrowserRenderFailures(): void
    {
        $fields = [];
        $columns = [];
        for ($index = 1; $index <= 12; $index++) {
            $name = "column{$index}";
            $columns[] = $name;
            $fields[] = ['columnName' => $name];
        }

        $view = $this->call('sanitizeTableView', [[
            'columnOrder' => $columns,
            'visibleColumns' => $columns,
        ], $fields]);

        $this->assertCount(10, $view['visibleColumns']);
        $this->assertSame(array_slice($columns, 0, 10), $view['visibleColumns']);
    }
}
