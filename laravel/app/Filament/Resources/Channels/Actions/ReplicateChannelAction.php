<?php

namespace App\Filament\Resources\Channels\Actions;

use App\Models\Channel;
use App\Models\ChannelModel;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ReplicateChannelAction extends Action
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
            ->modalHeading('复制渠道')
            ->modalDescription(fn (Channel $record): string => "确定要复制渠道「{$record->name}」吗？将创建一个新的渠道副本。")
            ->modalSubmitActionLabel('确认复制')
            ->action(fn (Channel $record) => $this->replicateChannel($record));
    }

    protected function replicateChannel(Channel $record): void
    {
        $newChannel = $record->replicate([
            'id',
            'slug',
            'failure_count',
            'success_count',
            'last_check_at',
            'last_failure_at',
            'last_success_at',
            'total_requests',
            'total_tokens',
            'total_cost',
            'avg_latency_ms',
            'success_rate',
            'coding_last_check_at',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);

        $newChannel->name = $record->name.' (副本)';
        $newChannel->status = 'disabled';
        $newChannel->failure_count = 0;
        $newChannel->success_count = 0;
        $newChannel->total_requests = 0;
        $newChannel->total_tokens = 0;
        $newChannel->total_cost = 0;
        $newChannel->avg_latency_ms = 0;
        $newChannel->success_rate = 1.0000;
        $newChannel->save();

        $groupData = [];
        foreach ($record->groups as $group) {
            $groupData[$group->id] = ['priority' => $group->pivot->priority];
        }
        $newChannel->groups()->attach($groupData);

        $tagIds = $record->tags()->pluck('id')->toArray();
        $newChannel->tags()->attach($tagIds);

        $channelModels = ChannelModel::where('channel_id', $record->id)->get();
        foreach ($channelModels as $model) {
            $newModel = $model->replicate(['id', 'channel_id', 'created_at', 'updated_at']);
            $newModel->channel_id = $newChannel->id;
            $newModel->save();
        }

        Notification::make()
            ->title('复制成功')
            ->body("渠道「{$record->name}」已成功复制为「{$newChannel->name}」")
            ->success()
            ->send();
    }
}
