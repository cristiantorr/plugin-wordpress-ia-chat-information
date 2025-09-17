jQuery(document).ready(function ($) {
  // Verifica compatibilidad con reconocimiento de voz
  /*  const SpeechRecognition =
    window.SpeechRecognition || window.webkitSpeechRecognition;

  if (!SpeechRecognition) {
    console.warn("Reconocimiento de voz no soportado en este navegador.");
  } else {
    const recognition = new SpeechRecognition();
    recognition.lang = "es-ES";
    recognition.interimResults = false;

    const micBtn = document.createElement("button");
    micBtn.textContent = "🎤 Hablar";
    micBtn.style.marginLeft = "10px";
    micBtn.id = "mic-button"; // por si lo quieres ocultar en CSS

    // Agrega el botón después del input
    const questionInput = document.querySelector("#question");
    if (questionInput) {
      questionInput.after(micBtn);
    }

    micBtn.addEventListener("click", () => {
      try {
        recognition.start();
      } catch (e) {
        console.error("Error al iniciar el reconocimiento:", e);
      }
    });

    recognition.onresult = (event) => {
      const speechResult = event.results[0][0].transcript;
      document.querySelector("#question").value = speechResult;

      document
        .querySelector("#gemini-qa-form")
        .dispatchEvent(new Event("submit"));
    };

    recognition.onerror = (event) => {
      console.error("Error en el reconocimiento de voz:", event.error);
      alert("Error en el reconocimiento de voz: " + event.error);
    };
  } */

  $("#gemini-qa-form").on("submit", function (e) {
    e.preventDefault();
    var question = $(this).find("input[name=question]").val();
    $("#gemini-qa-response")
      .addClass("gemini-qa-response")
      .html("<p>Loading...</p>");
    $.post(geminiQA.ajax_url, {
      action: "gemini_qa",
      question: question,
    }).done(function (res) {
      $("#gemini-qa-response").removeClass("gemini-qa-response").empty();
      $("#gemini-qa-response").html(
        "<strong>Respuesta:</strong> " + res.answer
      );
      $("#gemini-qa-form")[0].reset();
      //speakText(res.answer);
    });
  });
});

function speakText(text) {
  if ("speechSynthesis" in window) {
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = "en-EN";
    window.speechSynthesis.speak(utterance);
  } else {
    console.log("Speech synthesis not supported");
  }
}
