using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Drawing;
using System.Data;
using System.Linq;
using System.Text;
using System.Windows.Forms;

namespace MafiaRatings
{
    public partial class BorderLabel : UserControl
    {
        public BorderLabel()
        {
            InitializeComponent();
        }

        public string String
        {
            get { return label.Text; }
            set { label.Text = value; }
        }

        public ContentAlignment TextAlign
        {
            get { return label.TextAlign; }
            set { label.TextAlign = value; }
        }

        private void BorderLabel_EnabledChanged(object sender, EventArgs e)
        {
            label.Visible = Enabled;
        }
    }
}
