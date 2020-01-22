//To show selected tab on Group home page.
function showTabGroupHome(tab)
{
   if(tab==1) 
   { 
    $("#activitiesTab").css("display", "");
    $("#commitsTab").css("display", "none");
    $("#issuesTab").css("display", "none");
    $('#activities').addClass('selected');
		$('#commits').removeClass('selected');
		$('#issues').removeClass('selected');
   }
   else if(tab==2)
   {
    $("#activitiesTab").css("display", "none");
    $("#commitsTab").css("display", "");
    $("#issuesTab").css("display", "none");
		$('#activities').removeClass('selected');
		$('#commits').addClass('selected');
		$('#issues').removeClass('selected');
   }
   else
   {
    $("#activitiesTab").css("display", "none");
    $("#commitsTab").css("display", "none");
    $("#issuesTab").css("display", "");
    $('#activities').removeClass('selected');
		$('#commits').removeClass('selected');
		$('#issues').addClass('selected');
   }
}

//To show selected tab on GitHubDetails page.
function showTabGitHubDetails(tab)
{
   if(tab==1)
   {
     $("#panel1").css("display", "");
		 $("#panel2").css("display", "none");
		 $("#panel3").css("display", "none");
	 
		 $('#commits').addClass('selected');
		 $('#issues').removeClass('selected');
		 $('#files').removeClass('selected');
   }
   else if(tab==2)
   {
     $("#panel2").css("display", "");
		 $("#panel1").css("display", "none");
		 $("#panel3").css("display", "none");
	 
		 $('#issues').addClass('selected');
		 $('#commits').removeClass('selected');
		 $('#files').removeClass('selected');
   }
   else
   {   
     $("#panel3").css("display", "");
		 $("#panel1").css("display", "none");
		 $("#panel2").css("display", "none");
	 
		 $('#files').addClass('selected');
		 $('#commits').removeClass('selected');
		 $('#issues').removeClass('selected');
   }
}