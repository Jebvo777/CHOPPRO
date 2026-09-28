export type SyncState = 'PENDING_SYNC' | 'SYNCED' | 'ERROR';

export interface ShiftSummary {
  id: string;
  facilityName: string;
  postName: string;
  startsAt: string;
  endsAt: string;
  status: 'READY' | 'IN_PROGRESS' | 'COMPLETED' | 'NO_SHOW';
}

export interface OfflineEvent {
  id: string;
  type: 'CHECK_IN' | 'CHECK_OUT' | 'CHECKPOINT_SCAN' | 'INCIDENT';
  idempotencyKey: string;
  clientTime: string;
  payload: Record<string, unknown>;
  syncState: SyncState;
}
