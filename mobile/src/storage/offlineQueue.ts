import type {OfflineEvent} from '../types/domain';

const queue: OfflineEvent[] = [];

export function enqueue(event: OfflineEvent): void {
  queue.push(event);
}

export function listPending(): OfflineEvent[] {
  return queue.filter(item => item.syncState !== 'SYNCED');
}
