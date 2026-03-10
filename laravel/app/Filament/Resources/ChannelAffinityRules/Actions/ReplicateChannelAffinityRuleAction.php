<?php

namespace App\Filament\Resources\ChannelAffinityRules\Actions;

use App\Models\ChannelAffinityRule;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ReplicateChannelAffinityRuleAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'replicate';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('复制')
            ->icon(Heroicon::DocumentDuplicate)
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('复制渠道亲和性规则')
            ->modalDescription(fn (ChannelAffinityRule $record): string => "确定要复制规则「{$record->name}」吗？将创建一个新的规则副本。")
            ->modalSubmitActionLabel('确认复制')
            ->action(fn (ChannelAffinityRule $record) => $this->replicateRule($record));
    }

    protected function replicateRule(ChannelAffinityRule $record): void
    {
        $newRule = $record->replicate([
            'id',
            'hit_count',
            'last_hit_at',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);

        $newRule->name = $record->name.' (副本)';
        $newRule->hit_count = 0;
        $newRule->last_hit_at = null;
        $newRule->save();

        Notification::make()
            ->title('复制成功')
            ->body("规则「{$record->name}」已成功复制为「{$newRule->name}」")
            ->success()
            ->send();
    }
}
