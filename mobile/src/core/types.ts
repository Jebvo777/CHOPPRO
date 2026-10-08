export type Row = Record<string, any>;
export interface Session { access_token: string; refresh_token: string; user: Row; expires_in: number; csrf?: string }
export type EventType = 'ASSIGNMENT_CONFIRM' | 'INSTRUCTION_ACK' | 'POLICY_ACK' | 'CHECK_IN' | 'CHECK_OUT' | 'PATROL_START' | 'CHECKPOINT_SCAN' | 'PATROL_FINISH' | 'INCIDENT_CREATE' | 'INCIDENT_AMEND' | 'ATTENDANCE_EXPLAIN' | 'UPLOAD';
export interface QueuedEvent { id: string; type: EventType; client_time: string; device_id: string; payload: Row; state: 'PENDING' | 'SENDING' | 'SYNCED' | 'ERROR' | 'BLOCKED'; attempts: number; nextAttempt: number; error?: string; result?: Row }
export interface Snapshot { user: Row; tenant: Row; demo: boolean; server_time: string; assignments: Row[]; instructions: Row[]; attendance: Row[]; routes: Row[]; patrols: Row[]; incidents: Row[]; policy: Row | null; policy_acknowledged: boolean; permissions: string[]; demo_points: Row[]; settings?:Row }
export interface KeyValue { get(key: string): Promise<string | null>; set(key: string, value: string): Promise<void>; remove(key: string): Promise<void> }
export interface SendError extends Error { status?: number; code?: string }
