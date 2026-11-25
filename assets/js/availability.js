(function(){
'use strict';
// Share in-flight requests between the booking form and availability page.
// No persistent cache: a fresh lookup is made after the current request finishes.
const pending=new Map();
window.EventEaseAvailability={
 get(venue,date){
  const key=String(venue)+'|'+String(date);
  if(pending.has(key))return pending.get(key);
  const task=fetch('reservations.php?action=availability&venue_id='+encodeURIComponent(venue)+'&date='+encodeURIComponent(date),{cache:'no-store',credentials:'same-origin'}).then(async response=>{
   const data=await response.json();
   if(!response.ok||!data.ok)throw new Error(data.message||'Could not check schedule.');
   return data;
  }).finally(()=>{if(pending.get(key)===task)pending.delete(key);});
  pending.set(key,task);return task;
 },
 clear(){pending.clear();}
};
})();