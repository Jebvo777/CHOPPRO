import type { KeyValue, QueuedEvent, Row, SendError } from './types';
const copy = <T,>(value:T):T => JSON.parse(JSON.stringify(value)) as T;
export class EventQueue {
  private events: QueuedEvent[] = [];
  private running: Promise<void> | null = null;
  private writes: Promise<void> = Promise.resolve();
  constructor(private store: KeyValue, private send: (event: QueuedEvent) => Promise<Row>, private changed: () => void = () => {}) {}
  async load(): Promise<void> {
    this.events = JSON.parse(await this.store.get('events') ?? '[]') as QueuedEvent[];
    await this.change(() => {for (const event of this.events) if (event.state === 'SENDING') event.state = 'PENDING';});
  }
  list(): QueuedEvent[] { return copy(this.events); }
  async add(event: QueuedEvent): Promise<void> { return this.addBatch([event]); }
  async addBatch(events:QueuedEvent[]):Promise<void> {
    await this.change(() => {
      if (new Set(events.map(e=>e.id)).size!==events.length || events.some(e=>this.events.some(old=>old.id===e.id))) throw new Error('Событие уже сохранено');
      if (this.events.filter(item=>item.state!=='SYNCED').length+events.length>2000) throw new Error('Отправьте накопленные события перед новой записью');
      this.events.push(...copy(events));
    });
  }
  async retry(id: string): Promise<void> {
    await this.change(() => {const event=this.events.find(e=>e.id===id);if(!event||event.state==='SYNCED'||event.state==='SENDING')return;event.state='PENDING';event.nextAttempt=0;delete event.error;});
  }
  flush(force = false): Promise<void> {
    if (this.running) return this.running;
    this.running = this.perform(force).finally(() => { this.running = null; });
    return this.running;
  }
  private async perform(force: boolean): Promise<void> {
    await this.writes;
    for (const id of this.events.map(e=>e.id)) {
      const event=this.events.find(e=>e.id===id)!;
      if (event.state==='SYNCED'||event.state==='BLOCKED')continue;
      if(!force&&event.nextAttempt>Date.now())continue;
      const dependency=event.payload.depends_on as string|undefined;
      if(dependency&&this.events.find(e=>e.id===dependency)?.state!=='SYNCED')continue;
      await this.changeEvent(id,event=>{event.state='SENDING';});
      let result:Row;
      try {result=await this.send(copy(event));}
      catch(caught){const error=caught as SendError;await this.changeEvent(id,event=>{event.attempts++;event.error=error.message||'Не удалось отправить событие';event.state=error.status&&error.status>=400&&error.status<500&&![401,408,429].includes(error.status)?'BLOCKED':'ERROR';event.nextAttempt=Date.now()+Math.min(60000,1000*2**Math.min(event.attempts,6));});if([401,403].includes(error.status??0)||!error.status||error.status>=500)break;continue;}
      await this.changeEvent(id,event=>{event.result=result;event.state='SYNCED';delete event.error;event.nextAttempt=0;if(event.type==='UPLOAD')event.payload.file={name:event.payload.file.name,mime:event.payload.file.mime};});
    }
    const cutoff=Date.now()-14*86400000;
    await this.change(()=>{this.events=this.events.filter(event=>event.state!=='SYNCED'||Date.parse(event.client_time)>=cutoff||this.events.some(child=>child.state!=='SYNCED'&&child.payload.depends_on===event.id));});
  }
  private async changeEvent(id:string,fn:(event:QueuedEvent)=>void):Promise<void>{await this.change(()=>{const event=this.events.find(e=>e.id===id);if(event)fn(event);});}
  private async change(fn:()=>void):Promise<void> {
    const task=this.writes.then(async()=>{const previous=copy(this.events);try{fn();await this.store.set('events',JSON.stringify(this.events));}catch(error){this.events=previous;throw error;}this.changed();});
    this.writes=task.catch(()=>{});await task;
  }
}
