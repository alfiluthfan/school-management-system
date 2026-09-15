<?php
namespace App\Services\Notifications;

use App\Enums\Communication\NotificationStatus;
use App\Models\Communication\NotificationLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class NotificationMonitoringService
{
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->with(['recipientUser','student.user'])
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function stats(array $filters): array
    {
        $base = $this->filteredQuery($filters);
        $total = (clone $base)->count();
        $queued = (clone $base)->where('status', NotificationStatus::Queued->value)->count();
        $processing = (clone $base)->where('status', NotificationStatus::Processing->value)->count();
        $sent = (clone $base)->where('status', NotificationStatus::Sent->value)->count();
        $failed = (clone $base)->where('status', NotificationStatus::Failed->value)->count();
        $cancelled = (clone $base)->where('status', NotificationStatus::Cancelled->value)->count();

        $terminal = $sent + $failed;

        return [
            'total' => $total,
            'queued' => $queued,
            'processing' => $processing,
            'sent' => $sent,
            'failed' => $failed,
            'cancelled' => $cancelled,
            'delivery_rate_percent' => $terminal > 0
                ? round(($sent / $terminal) * 100, 2)
                : 0.0,
        ];
    }

    private function filteredQuery(array $filters): Builder
    {
        $query = NotificationLog::query();

        foreach (['status','type','channel'] as $key) {
            if (!empty($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }

        if (!empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        if (!empty($filters['search'])) {
            $search = '%'.trim((string) $filters['search']).'%';

            $query->where(function (Builder $q) use ($search): void {
                $q->where('recipient','like',$search)
                    ->orWhere('subject','like',$search)
                    ->orWhere('message','like',$search)
                    ->orWhere('provider_message_id','like',$search)
                    ->orWhereHas('recipientUser', fn (Builder $uq) =>
                        $uq->where('name','like',$search)
                           ->orWhere('phone','like',$search))
                    ->orWhereHas('student.user', fn (Builder $sq) =>
                        $sq->where('name','like',$search));
            });
        }

        return $query;
    }
}
