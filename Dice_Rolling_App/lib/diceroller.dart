import 'package:flutter/material.dart';
import 'dart:math';

class DiceRoller extends StatefulWidget {
  const DiceRoller({super.key});
  @override
  State<DiceRoller> createState() {
    return _DiceRollerState();
  }
}

class _DiceRollerState extends State<DiceRoller> {
  var ActiveDiceImage = 'assets/dice3.png';
  void Dice_roll() {
    var dice = Random().nextInt(6) + 1;
    setState(() {
      ActiveDiceImage = 'assets/dice$dice.png';
    });
  }

  @override
  Widget build(context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Image.asset(
          ActiveDiceImage,
          width: 100,
        ),
        const SizedBox(
          height: 20,
        ),
        TextButton(
          onPressed: Dice_roll,
          style: TextButton.styleFrom(
            foregroundColor: Colors.white,
            textStyle: const TextStyle(
              fontSize: 28,
            ),
          ),
          child: const Text('Roll'),
        )
      ],
    );
  }
}
