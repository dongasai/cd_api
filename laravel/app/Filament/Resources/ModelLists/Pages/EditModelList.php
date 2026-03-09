<?php

namespace App\Filament\Resources\ModelLists\Pages;

use App\Filament\Resources\ModelLists\ModelListResource;
use App\Services\Pricing\OpenRouterPricingService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditModelList extends EditRecord
{
    protected static string $resource = ModelListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('fetchPricing')
                ->label('价格获取')
                ->icon('heroicon-o-currency-dollar')
                ->color('warning')
                ->action(function () {
                    $record = $this->getRecord();
                    $huggingFaceId = $record->hugging_face_id;
                    $commonName = $record->common_name;

                    if (empty($huggingFaceId) && empty($commonName)) {
                        Notification::make()
                            ->title('无法获取价格')
                            ->body('请先填写 Hugging Face ID 或 通用名字')
                            ->warning()
                            ->send();

                        return;
                    }

                    $pricingService = app(OpenRouterPricingService::class);
                    // 优先使用 hugging_face_id，查不到再用 common_name
                    $pricing = null;
                    if (! empty($huggingFaceId)) {
                        $pricing = $pricingService->getPricing($huggingFaceId);
                    }
                    if ($pricing === null && ! empty($commonName)) {
                        $pricing = $pricingService->getPricing(null, $commonName);
                    }

                    if ($pricing === null) {
                        Notification::make()
                            ->title('获取失败')
                            ->body('未找到该模型的价格信息')
                            ->danger()
                            ->send();

                        return;
                    }

                    // OpenRouter 返回的是每 token 价格，需要转换为每百万 token 价格
                    $multiplier = 1_000_000;

                    $record->pricing_prompt = $pricing['prompt'] !== null ? $pricing['prompt'] * $multiplier : null;
                    $record->pricing_completion = $pricing['completion'] !== null ? $pricing['completion'] * $multiplier : null;
                    $record->pricing_input_cache_read = $pricing['input_cache_read'] !== null ? $pricing['input_cache_read'] * $multiplier : null;

                    // 如果有上下文长度，也更新
                    if ($pricing['context_length'] !== null && $record->context_length === null) {
                        $record->context_length = $pricing['context_length'];
                    }

                    $record->save();

                    // 刷新表单数据
                    $this->fillForm();

                    Notification::make()
                        ->title('获取成功')
                        ->body('价格信息已更新')
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
