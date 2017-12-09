using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Threading.Tasks;

namespace TournamentSeating
{
    interface ISeatingCalculatorListener
    {
        void SeatingsUpdated();
        void CalculationFinished();
    }
}
