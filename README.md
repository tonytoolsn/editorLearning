yarn add summernote

.addEntry('summernote', './assets/js/summernote.js')

{% block stylesheets %}
  {{ parent() }}
  {{ encore_entry_link_tags('summernote') }}
{% endblock %}

{% block javascripts %}
  {{ parent() }}
  {{ encore_entry_script_tags('summernote') }}
{% endblock %}
