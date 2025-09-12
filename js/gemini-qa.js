jQuery(document).ready(function ($) {
  $("#gemini-qa-form").on("submit", function (e) {
    e.preventDefault();
    var question = $(this).find("input[name=question]").val();

    $.post(geminiQA.ajax_url, {
      action: "gemini_qa",
      question: question,
    }).done(function (res) {
      $("#gemini-qa-response").html(
        "<p><strong>Respuesta:</strong> " + res.answer + "</p>"
      );
    });
  });
});
