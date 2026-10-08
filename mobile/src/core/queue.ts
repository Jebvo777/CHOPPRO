import type { KeyValue, QueuedEvent, Row, SendError } from './types';
export class EventQueue {
  private events: QueuedEvent[] = [];
  private running: Promise<void> | null = null;
  private writes: Promise<void> = Promise.resolve();
  constructor(private store: KeyValue, private send: (event: QueuedEvent) => Promise<Row>, private changed: () => void = () => {}) {}
  async load(): Promise<void> {
    this.events = JSON.parse(await this.store.get('events') ?? '[]') as QueuedEvent[];
    for (const event of this.events) if (event.state === 'SENDING') event.state = 'PENDING';
    await this.persist();
  }
  list(): QueuedEvent[] { return this.events.map(event => ({ ...event, payload: { ...event.payload } })); }
  async add(event: QueuedEvent): Promise<void> {
    if (this.events.some(item => item.id === event.id)) throw new Error('Событие уже сохранено');
    if (this.events.filter(item => item.state !== 'SYNCED').length >= 2000) throw new Error('Отправьте накопленные события перед новой записью');
    this.events.push(structuredClone(event));
    await this.persist();
  }
  async retry(id: string): Promise<void> {
    const event = this.events.find(item => item.id === id);
    if (!event || event.state === 'SYNCED' || event.state === 'SENDING') return;
    event.state = 'PENDING'; event.nextAttempt = 0; delete event.error;
    await this.persist();
  }
  flush(force = false): Promise<void> {
    if (this.running) return this.running;
    this.running = this.perform(force).finally(() => { this.running = null; });
    return this.running;
  }
  private async perform(force: boolean): Promise<void> {
    for (const event of this.events) {
      if (event.state === 'SYNCED' || event.state === 'BLOCKED') continue;
      if (!force && event.nextAttempt > Date.now()) continue;
      const dependency = event.payload.depends_on as string | undefined;
      if (dependency && this.events.find(item => item.id === dependency)?.state !== 'SYNCED') continue;
      event.state = 'SENDING'; await this.persist();
      try {
        event.result = await this.send(structuredClone(event));
        event.state = 'SYNCED'; delete event.error; event.nextAttempt = 0;
        if (event.type === 'UPLOAD') event.payload.file = {name:event.payload.file.name,mime:event.payload.file.mime};
      } catch (caught) {
        const error = caught as SendError;
        event.attempts++; event.error = error.message || 'Не удалось отправить событие';
        event.state = error.status && error.status >= 400 && error.status < 500 && ![401,408,429].includes(error.status) ? 'BLOCKED' : 'ERROR';
        event.nextAttempt = Date.now() + Math.min(60000, 1000 * 2 ** Math.min(event.attempts, 6));
        await this.persist();
        if ([401,403].includes(error.status ?? 0) || !error.status || error.status >= 500) break;
        continue;
      }
      await this.persist();
    }
    const cutoff = Date.now() - 14 * 86400000;
    const retained = this.events.filter(event => event.state !== 'SYNCED' || Date.parse(event.client_time) >= cutoff || this.events.some(child => child.state !== 'SYNCED' && child.payload.depends_on === event.id));
    if (retained.length !== this.events.length) { this.events = retained; await this.persist(); }
  }
  private async persist(): Promise<void> {
    const serialized = JSON.stringify(this.events);
    const task = this.writes.then(() => this.store.set('events', serialized));
    this.writes = task.catch(() => {});
    await task; this.changed();
  }
}
