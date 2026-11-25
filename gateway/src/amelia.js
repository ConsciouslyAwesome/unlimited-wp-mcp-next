// Optional native extensions, separate from the pinned upstream compatibility contract.
const id={type:'integer',minimum:1};
const assignment={employee_id:id,location_id:id};
const mutation={
  appointment_id:id,
  expected_state:{type:'string',pattern:'^[a-f0-9]{64}$'},
  new_start:{type:'string',pattern:'^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}$'},
  timezone:{type:'string',minLength:1,maxLength:100},
  notify_participants:{type:'boolean',default:true},
};
export const AMELIA_TOOLS=[
  ['get_appointment',true,{appointment_id:id},['appointment_id']],
  ['preview_appointment',true,{...mutation,...assignment,internal_notes:{type:'string',maxLength:10000}},['appointment_id','expected_state']],
  ['reschedule_appointment',false,{...mutation,dry_run:{type:'boolean',default:true}},['appointment_id','expected_state','new_start','timezone']],
  ['update_appointment',false,{...mutation,...assignment,internal_notes:{type:'string',maxLength:10000},dry_run:{type:'boolean',default:true}},['appointment_id','expected_state']],
].map(([suffix,read,properties,required])=>({
  name:'amelia_'+suffix,
  description:'Amelia 9.10 '+suffix.replaceAll('_',' ')+'. Target a registered site explicitly. Retrieve expected_state before previewing or editing. Time, internal notes, employee and location changes are supported for verified single-booking profiles. Linked external meeting transfers require separate acceptance. notify_participants=false is rejected because Amelia may notify employees. Unsupported installations reject the operation. '+(read?'Read operation.':'Requires exact-operation WordPress browser approval; inspect the receipt after uncertainty.'),
  inputSchema:{type:'object',properties:{...properties,...(read?{}:{approval_id:{type:'string',pattern:'^[a-f0-9]{48}$'}})},required,additionalProperties:false},
  annotations:{readOnlyHint:read,destructiveHint:!read,idempotentHint:read,openWorldHint:true},
}));

export function ameliaCatalog() {
  return AMELIA_TOOLS.map(tool=>({...tool,inputSchema:{...tool.inputSchema,
    properties:{...tool.inputSchema.properties,site:{type:'string',description:'Registered site ID or unique alias.'}},
    required:[...tool.inputSchema.required,'site'],
  }}));
}
