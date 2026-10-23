<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\NetworkNode;
use App\Models\NetworkNodeActionView;
use App\Models\Task;
use App\Models\TaskInfrastructureAction;
use App\Models\User;
use App\Notifications\AppNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Penanda "ada perangkat baru terpasang" di Master Node. Node dianggap baru
 * untuk user selama ada aksi pasang/ganti/sambung port yang lebih baru dari
 * terakhir kali user membuka node itu. Hanya node di POP scope user yang dihitung.
 */
class NetworkNodeAttentionService
{
    /** @var list<string> */
    public const ACTION_TYPES = ['install', 'replace', 'port_connect'];

    public function __construct(private readonly EffectiveAccessService $access) {}

    /**
     * @return list<int>
     */
    public function unseenNodeIds(User $user): array
    {
        /** @var Collection<int, string> $lastPerformedAt */
        $lastPerformedAt = TaskInfrastructureAction::query()
            ->whereIn('action_type', self::ACTION_TYPES)
            ->whereNotNull('device_network_node_id')
            ->groupBy('device_network_node_id')
            ->selectRaw('device_network_node_id, MAX(performed_at) AS last_performed_at')
            ->pluck('last_performed_at', 'device_network_node_id');

        if ($lastPerformedAt->isEmpty()) {
            return [];
        }

        $visibleNodeIds = NetworkNode::query()
            ->when(! $this->access->hasAllPopAccess($user), fn ($query) => $query->inPops($this->access->getAllowedPopIds($user)))
            ->whereIn('id', $lastPerformedAt->keys())
            ->pluck('id');

        $seenAt = NetworkNodeActionView::query()
            ->where('user_id', $user->id)
            ->whereIn('network_node_id', $visibleNodeIds)
            ->pluck('seen_at', 'network_node_id');

        return $visibleNodeIds
            ->filter(fn (int $nodeId): bool => ! $seenAt->has($nodeId)
                || Carbon::parse($lastPerformedAt[$nodeId])->greaterThan(Carbon::parse($seenAt[$nodeId])))
            ->values()
            ->all();
    }

    public function unseenCount(User $user): int
    {
        return count($this->unseenNodeIds($user));
    }

    public function markSeen(User $user, NetworkNode $node): void
    {
        NetworkNodeActionView::query()->updateOrCreate(
            ['user_id' => $user->id, 'network_node_id' => $node->id],
            ['seen_at' => now()],
        );
    }

    public function notifyInstalled(NetworkNode $node, Task $task, User $actor): void
    {
        $recipients = $this->access->usersWithPermission('network_nodes.view')->with('role')->get()
            ->reject(fn (User $user): bool => $user->is($actor))
            ->filter(fn (User $user): bool => $this->canSeeNode($user, $node));

        foreach ($recipients as $recipient) {
            $recipient->notify(new AppNotification(
                title: 'Perangkat baru terpasang: '.$node->code,
                message: "{$actor->name} melaporkan pemasangan di {$node->breadcrumbLabel()} (task {$task->task_number}).",
                actionUrl: route('network.nodes.show', $node),
                type: NotificationType::INFO,
            ));
        }
    }

    private function canSeeNode(User $user, NetworkNode $node): bool
    {
        if ($this->access->hasAllPopAccess($user)) {
            return true;
        }

        return NetworkNode::query()
            ->inPops($this->access->getAllowedPopIds($user))
            ->whereKey($node->id)
            ->exists();
    }
}
