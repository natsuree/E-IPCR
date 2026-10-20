import {employee, rating, makeRecord, STAGES} from './model.js';
// Roster membership is explicit; existing installations receive an additive migration.
export const PROGRESS_STATES=['Not started','Draft','Submitted','Returned','Under review','Completed'];
export function inScope(actor,person){
 if(!actor||!actor.active)return false;
 if(employee(actor))return actor.id===person.id;
 if(actor.role==='Dean')return actor.unit===person.unit;
 if(actor.role==='Department Head')return actor.unit===person.unit&&actor.department===person.department;
 return ['HR Personnel','Vice President','President'].includes(actor.role);
}
export function approvedResult(r){
 if(r.status!=='Completed'||!r.history?.some(h=>h.role==='President'&&h.action==='Final approval · Completed'))return null;
 const result=r.finalRating;
 return result&&Number.isFinite(result.total)?result:null;
}
export function upgradeRoster(db,addSamples=false){
 if(db.rosterVersion===1)return db;
 for(const u of db.users)if(!Array.isArray(u.periodIds))u.periodIds=employee(u)?db.periods.map(p=>p.id):[];
 for(const r of db.records)if(r.status==='Completed'&&!r.finalRating&&r.history?.some(h=>h.role==='President'&&h.action==='Final approval · Completed'))r.finalRating={...rating(r,r.ratingSettings||db.settings),approvedAt:r.history.findLast(h=>h.role==='President').at};
 if(addSamples){
  const extraUnit={id:'cas',name:'College of Arts and Sciences',departments:['Mathematics','Biology']};
  if(!db.units.some(u=>u.name===extraUnit.name))db.units.push(extraUnit);
  const specs=[
   ['eng-civil-head','Paolo Reyes','Department Head','College of Engineering','Civil Engineering'],
   ['cas-dean','Marina Acosta','Dean',extraUnit.name,'Mathematics'],
   ['math-head','Carlo Garcia','Department Head',extraUnit.name,'Mathematics'],
   ['bio-head','Liza Mercado','Department Head',extraUnit.name,'Biology'],
   ['finance-head','Ramon Castillo','Department Head','Administrative Services','Finance Office']
  ];
  for(const [id,name,role,unit,department] of specs)if(!db.users.some(u=>u.id===id))db.users.push({id,name,role,unit,department,email:id+'@demo.mmsu.edu.ph',password:'Demo@2026',position:role,type:unit==='Administrative Services'?'Non-Teaching':'Teaching',active:true,periodIds:[]});
  const samples=[
   ['civil-1','Ana Bautista','College of Engineering','Civil Engineering','Completed',4.8],
   ['civil-2','Marco Valdez','College of Engineering','Civil Engineering','Completed',4.8],
   ['civil-3','Nina Aquino','College of Engineering','Civil Engineering','HR Personnel',4.9],
   ['civil-4','Jose Santiago','College of Engineering','Civil Engineering',null],
   ['comp-1','Diana Torres','College of Engineering','Computer Engineering','Draft'],
   ['comp-2','Leo Pascual','College of Engineering','Computer Engineering','Returned'],
   ['comp-3','Mia Domingo','College of Engineering','Computer Engineering',null],
   ['math-1','Bea Fernandez',extraUnit.name,'Mathematics','HR Personnel'],
   ['math-2','Anton Lopez',extraUnit.name,'Mathematics','Vice President'],
   ['math-3','Rica Manuel',extraUnit.name,'Mathematics','Completed',4.5],
   ['math-4','Tom Villanueva',extraUnit.name,'Mathematics',null],
   ['bio-1','Ella Ramos',extraUnit.name,'Biology','President'],
   ['bio-2','Noel Agustin',extraUnit.name,'Biology','Department Head'],
   ['bio-3','Irene Tan',extraUnit.name,'Biology','Dean'],
   ['finance-1','Grace Abad','Administrative Services','Finance Office','HR Personnel'],
   ['finance-2','Ben Flores','Administrative Services','Finance Office','Vice President'],
   ['finance-3','Rose Diaz','Administrative Services','Finance Office',null],
   ['civil-5','Victor Cruz','College of Engineering','Civil Engineering','President']
  ];
  const period=db.periods.find(p=>p.active)?.id||db.periods[0]?.id;
  for(const [key,name,unit,department,status,score=4] of samples){
   const id='sample-'+key;if(db.users.some(u=>u.id===id)||!period)continue;
   const type=unit==='Administrative Services'?'Non-Teaching':'Teaching';
   const person={id,name,unit,department,type,role:type+' Employee',email:key+'@demo.mmsu.edu.ph',password:'Demo@2026',position:type==='Teaching'?'Assistant Professor':'Administrative Officer',active:true,periodIds:[period]};db.users.push(person);
   if(!status)continue;
   const r=makeRecord(person,period,db.settings);r.id='DEMO-'+key;r.status=status;
   r.rows.forEach(row=>Object.assign(row,{q:score,e:score,t:score,actual:'Sample accomplishment with supporting evidence.'}));
   if(status!=='Draft'){
    r.submissions.push({at:r.created,rows:structuredClone(r.rows),ratingSettings:structuredClone(db.settings)});
    r.history.push({at:r.created,actor:name,role:person.role,action:'Submitted',comment:'Fictional sample.'});
    const end=status==='Returned'?2:STAGES.indexOf(status);
    for(const role of STAGES.slice(0,end)){const reviewer=db.users.find(u=>u.role===role&&inScope(u,person));r.history.push({at:r.created,actor:reviewer?.name||role,role,action:role==='President'?'Final approval · Completed':'Approved and forwarded',comment:'Sample approval.'});}
    if(status==='Returned')r.history.push({at:r.created,actor:'Mika Santos',role:'HR Personnel',action:'Returned for correction',comment:'Please add evidence.'});
   }
   if(status==='Completed')r.finalRating={...rating(r,db.settings),approvedAt:r.created};
   db.records.push(r);
  }
 }
 db.rosterVersion=1;return db;
}
export function employeeProgress(db,actor,filters={}){
 const periods=db.periods.filter(p=>!filters.period||p.id===filters.period);
 const latest=rows=>[...rows].sort((a,b)=>(b.updated||b.created).localeCompare(a.updated||a.created)||b.id.localeCompare(a.id))[0];
 const result=[];
 for(const person of db.users.filter(u=>employee(u)&&u.active&&inScope(actor,u))){
  if(!['unit','department','type'].every(k=>!filters[k]||filters[k]===person[k]))continue;
  for(const period of periods.filter(p=>person.periodIds?.includes(p.id))){
   const records=db.records.filter(r=>r.owner===person.id&&r.period===period.id);
   const completed=latest(records.filter(r=>r.status==='Completed'&&r.history?.some(h=>h.role==='President'&&h.action==='Final approval · Completed')));
   const record=completed||latest(records.filter(r=>r.status!=='Draft'))||latest(records);
   const finalRecord=latest(records.filter(r=>approvedResult(r)));
   const state=!record?'Not started':record.status==='Department Head'?'Submitted':STAGES.slice(1,5).includes(record.status)?'Under review':record.status;
   const submitted=records.some(r=>r.submissions?.length>0);
   const entry={id:person.id,name:person.name,unit:person.unit,department:person.department,type:person.type,period:period.id,state,submitted,completed:!!completed,reviewer:STAGES.slice(0,5).includes(record?.status)?record.status:'',record,records,finalRating:finalRecord?approvedResult(finalRecord):null};
   if(filters.status&&filters.status!==state&&filters.status!==record?.status)continue;
   if(filters.submission==='yes'&&!submitted||filters.submission==='no'&&submitted)continue;
   if(filters.search&&!`${person.name} ${person.unit} ${person.department} ${records.map(r=>r.id).join(' ')}`.toLowerCase().includes(filters.search.toLowerCase()))continue;
   result.push(entry);
  }
 }
 return result.sort((a,b)=>a.unit.localeCompare(b.unit)||a.department.localeCompare(b.department)||a.name.localeCompare(b.name));
}
export function progressTotals(rows){const expected=rows.length,submitted=rows.filter(r=>r.submitted).length,completed=rows.filter(r=>r.completed).length;return {expected,submitted,completed,submissionPercent:expected?Math.round(submitted/expected*100):0,completionPercent:expected?Math.round(completed/expected*100):0,counts:Object.fromEntries(PROGRESS_STATES.map(s=>[s,rows.filter(r=>r.state===s).length]))};}
export function departmentLeaders(rows,facultyOnly=false){
 const rated=rows.filter(r=>r.finalRating&&(!facultyOnly||r.type==='Teaching'));
 const best=Math.max(...rated.map(r=>r.finalRating.total));
 return rated.filter(r=>Math.abs(r.finalRating.total-best)<1e-9);
}
export function progressCsv(rows,db){const cell=v=>'"'+String(v??'').replace(/^[\s]*[=+@\-]/,"'$&").replaceAll('"','""')+'"';return '\uFEFF'+[['Employee','College / office','Department','Employment type','Evaluation period','Progress','Current reviewer','Has submitted','Final approved rating'],...rows.map(r=>[r.name,r.unit,r.department,r.type,db.periods.find(p=>p.id===r.period)?.name,r.state,r.reviewer,r.submitted?'Yes':'No',r.finalRating?.total.toFixed(2)||''])].map(row=>row.map(cell).join(',')).join('\r\n');}
