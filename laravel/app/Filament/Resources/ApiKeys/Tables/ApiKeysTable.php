<?php

namespace App\Filament\Resources\ApiKeys\Tables;

use App\Models\ApiKey;
use App\Models\Channel;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ApiKeysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('名称')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('key')
                    ->label('密钥')
                    ->copyable()
                    ->copyMessage('密钥已复制到剪贴板')
                    ->copyMessageDuration(1500)
                    ->formatStateUsing(fn ($state) => $state ? $state : '-'),

                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => '启用',
                        'revoked' => '已撤销',
                        'expired' => '已过期',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'active',
                        'danger' => 'revoked',
                        'warning' => 'expired',
                    ]),

                TextColumn::make('expires_at')
                    ->label('过期时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ? $state->format('Y-m-d H:i') : '永不过期'),

                TextColumn::make('last_used_at')
                    ->label('最后使用')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ? $state->format('Y-m-d H:i') : '从未使用'),

                TextColumn::make('channel_restriction')
                    ->label('渠道限制')
                    ->formatStateUsing(function (ApiKey $record) {
                        $whitelist = $record->getAllowedChannelIds();
                        $blacklist = $record->getNotAllowedChannelIds();

                        if (empty($whitelist) && empty($blacklist)) {
                            return '无限制';
                        }

                        $parts = [];

                        if (! empty($whitelist)) {
                            $names = Channel::whereIn('id', $whitelist)->pluck('name')->join(', ');
                            $parts[] = '白名单: '.$names;
                        }

                        if (! empty($blacklist)) {
                            $names = Channel::whereIn('id', $blacklist)->pluck('name')->join(', ');
                            $parts[] = '黑名单: '.$names;
                        }

                        return implode(' | ', $parts);
                    })
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'active' => '启用',
                        'revoked' => '已撤销',
                        'expired' => '已过期',
                    ]),
            ])
            ->recordUrl(fn (ApiKey $record): string => route('filament.admin.resources.api-keys.view', $record))
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('regenerateKey')
                    ->label('重新生成密钥')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('重新生成密钥')
                    ->modalDescription('确定要重新生成密钥吗？旧密钥将立即失效。')
                    ->modalSubmitActionLabel('确认生成')
                    ->action(fn (ApiKey $record) => self::regenerateKey($record)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function regenerateKey(ApiKey $record): void
    {
        $plainKey = 'sk-'.Str::random(48);

        $record->update([
            'key' => $plainKey,
            'key_hash' => hash('sha256', $plainKey),
            'key_prefix' => substr($plainKey, 0, 12),
        ]);

        Notification::make()
            ->title('密钥已重新生成')
            ->success()
            ->send();
    }
}
